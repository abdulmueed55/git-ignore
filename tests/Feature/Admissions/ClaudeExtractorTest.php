<?php

namespace Tests\Feature\Admissions;

use Anthropic\Client;
use App\Services\Admissions\ClaudeDocumentExtractor;
use App\Services\Admissions\DocumentSchemas;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/** Exercises the real SDK request/response path with a fake HTTP transport — no network. */
class ClaudeExtractorTest extends TestCase
{
    private function transport(int $status, array $body): ClientInterface
    {
        return new class($status, $body) implements ClientInterface
        {
            public ?array $sent = null;

            public function __construct(private int $status, private array $body) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sent = json_decode((string) $request->getBody(), true);

                return new Response($this->status, ['Content-Type' => 'application/json', 'request-id' => 'req_test'], json_encode($this->body));
            }
        };
    }

    private function message(string $text, string $stop = 'end_turn'): array
    {
        return [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stop, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
        ];
    }

    private function extractor(ClientInterface $t): ClaudeDocumentExtractor
    {
        return new ClaudeDocumentExtractor('sk-test', 'claude-opus-5-5', new Client(apiKey: 'sk-test', requestOptions: ['transporter' => $t, 'maxRetries' => 0]));
    }

    public function test_reads_structured_json_and_sends_image_with_schema(): void
    {
        $json = json_encode(['name' => 'Usman Tariq', 'father_name' => 'Tariq Mehmood', 'cnic_number' => '37405-1234567-1', 'date_of_birth' => '2006-05-14', 'gender' => 'male', 'document_type_matches' => true, 'confidence' => 93, 'issues' => []]);
        $t = $this->transport(200, $this->message($json));

        $r = $this->extractor($t)->extract('cnic', 'binary-image', 'image/jpeg');

        $this->assertSame('done', $r['status']);
        $this->assertSame('Usman Tariq', $r['data']['name']);
        $this->assertSame(93, $r['confidence']);
        $this->assertSame('claude-opus-5-5', $t->sent['model']);
        $this->assertSame('image', $t->sent['messages'][0]['content'][0]['type']);
        $this->assertSame('image/jpeg', $t->sent['messages'][0]['content'][0]['source']['media_type']);
        $this->assertSame('json_schema', $t->sent['output_config']['format']['type']);
        $this->assertSame(DocumentSchemas::for('cnic')['required'], $t->sent['output_config']['format']['schema']['required']);
    }

    public function test_pdf_is_sent_as_document_block(): void
    {
        $t = $this->transport(200, $this->message('{"name":null,"father_name":null,"district":"Rawalpindi","document_type_matches":true,"confidence":80,"issues":[]}'));
        $r = $this->extractor($t)->extract('domicile', '%PDF-1.4', 'application/pdf');

        $this->assertSame('done', $r['status']);
        $this->assertSame('document', $t->sent['messages'][0]['content'][0]['type']);
    }

    public function test_refusal_and_truncation_are_reported_as_failures(): void
    {
        $r = $this->extractor($this->transport(200, $this->message('', 'refusal')))->extract('cnic', 'x', 'image/png');
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('declined', $r['error']);

        $r = $this->extractor($this->transport(200, $this->message('{"name":', 'max_tokens')))->extract('cnic', 'x', 'image/png');
        $this->assertSame('failed', $r['status']);
    }

    public function test_api_errors_become_readable_messages(): void
    {
        $r = $this->extractor($this->transport(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]))->extract('cnic', 'x', 'image/png');
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('API key', $r['error']);
    }
}
