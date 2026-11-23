package pk.edu.fglacollege.app;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowInsets;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.Toast;

/**
 * FG Liaquat Ali Degree College app: a thin, secure shell around the college portal.
 * First screen (bundled start.html) lets the user pick Student portal or Staff ERP;
 * after that the app opens that part of the college server directly.
 * Only the college server is loaded inside the app; every other link opens in the browser.
 */
public class MainActivity extends Activity {

    private static final String START_PAGE = "file:///android_asset/start.html";
    private static final String OFFLINE_PAGE = "file:///android_asset/offline.html";
    private static final int FILE_CHOOSER = 41;

    private WebView web;
    private ProgressBar progress;
    private SharedPreferences prefs;
    private ValueCallback<Uri[]> fileCallback;
    private long lastBack;

    @SuppressLint({"SetJavaScriptEnabled", "AddJavascriptInterface"})
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences("fgla", MODE_PRIVATE);

        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.parseColor("#0a3d26"));
        web = new WebView(this);
        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setMax(100);
        progress.setVisibility(View.GONE);
        root.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        root.addView(progress, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 8));
        setContentView(root);

        // Keep content clear of the status and navigation bars (edge-to-edge on Android 15).
        root.setOnApplyWindowInsetsListener((v, insets) -> {
            int top, bottom, left, right;
            if (Build.VERSION.SDK_INT >= 30) {
                android.graphics.Insets i = insets.getInsets(WindowInsets.Type.systemBars() | WindowInsets.Type.ime());
                top = i.top; bottom = i.bottom; left = i.left; right = i.right;
            } else {
                top = insets.getSystemWindowInsetTop(); bottom = insets.getSystemWindowInsetBottom();
                left = insets.getSystemWindowInsetLeft(); right = insets.getSystemWindowInsetRight();
            }
            v.setPadding(left, top, right, bottom);
            return insets;
        });

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setBuiltInZoomControls(true);
        s.setDisplayZoomControls(false);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(true);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setUserAgentString(s.getUserAgentString() + " FGLAApp/" + BuildConfig.VERSION_NAME);
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false);

        web.addJavascriptInterface(new Bridge(), "FGLA");
        web.setWebViewClient(new Client());
        web.setWebChromeClient(new Chrome());
        web.setDownloadListener((url, userAgent, contentDisposition, mimeType, length) -> download(url, userAgent, contentDisposition, mimeType));

        if (savedInstanceState != null) {
            web.restoreState(savedInstanceState);
        } else {
            String start = prefs.getString("start", null);
            web.loadUrl(start != null && isServerUrl(start) ? start : START_PAGE);
        }
    }

    // ------------------------------------------------------------------ helpers

    private String server() {
        return prefs.getString("server", BuildConfig.SERVER_URL);
    }

    /** True for pages of the configured college server (https only). */
    private boolean isServerUrl(String url) {
        Uri u = Uri.parse(url);
        Uri srv = Uri.parse(server());
        return "https".equals(u.getScheme()) && u.getHost() != null && u.getHost().equalsIgnoreCase(srv.getHost());
    }

    private void openOutside(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, "No app can open this link.", Toast.LENGTH_SHORT).show();
        }
    }

    /** CSV exports, PDFs and documents: saved to Downloads with the signed-in session. */
    private void download(String url, String userAgent, String contentDisposition, String mimeType) {
        if (!isServerUrl(url)) {
            openOutside(Uri.parse(url));
            return;
        }
        try {
            String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
            DownloadManager.Request r = new DownloadManager.Request(Uri.parse(url));
            r.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url));
            r.addRequestHeader("User-Agent", userAgent);
            r.setMimeType(mimeType);
            r.setTitle(name);
            r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            ((DownloadManager) getSystemService(DOWNLOAD_SERVICE)).enqueue(r);
            Toast.makeText(this, "Downloading " + name, Toast.LENGTH_SHORT).show();
        } catch (Exception e) {
            Toast.makeText(this, "Could not download the file.", Toast.LENGTH_SHORT).show();
        }
    }

    // ------------------------------------------------------------------ start-screen bridge

    /** Called only from the bundled start page (checked on the UI thread). */
    private class Bridge {
        @JavascriptInterface
        public String server() {
            return MainActivity.this.server();
        }

        @JavascriptInterface
        public void open(String path) {
            runOnUiThread(() -> {
                if (web.getUrl() == null || !web.getUrl().startsWith("file:///android_asset/")) return;
                if (path == null || !path.startsWith("/") || path.startsWith("//")) return;
                String url = MainActivity.this.server() + path;
                prefs.edit().putString("start", url).apply();
                web.loadUrl(url);
            });
        }

        /** "Try again" on the offline page: back to where the user was heading. */
        @JavascriptInterface
        public void retry() {
            runOnUiThread(() -> {
                if (web.getUrl() == null || !web.getUrl().startsWith("file:///android_asset/")) return;
                String start = prefs.getString("start", null);
                web.loadUrl(start != null && isServerUrl(start) ? start : START_PAGE);
            });
        }

        @JavascriptInterface
        public boolean setServer(String url) {
            Uri u = Uri.parse(url == null ? "" : url.trim());
            if (!"https".equals(u.getScheme()) || u.getHost() == null || u.getHost().isEmpty()) return false;
            String clean = "https://" + u.getHost() + (u.getPort() > 0 ? ":" + u.getPort() : "");
            runOnUiThread(() -> {
                if (web.getUrl() == null || !web.getUrl().startsWith("file:///android_asset/")) return;
                prefs.edit().putString("server", clean).remove("start").apply();
            });
            return true;
        }
    }

    // ------------------------------------------------------------------ WebView clients

    private class Client extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            String url = request.getUrl().toString();
            if (url.startsWith("file:///android_asset/") || isServerUrl(url)) return false;
            openOutside(request.getUrl()); // other websites, tel:, mailto:, WhatsApp …
            return true;
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) view.loadUrl(OFFLINE_PAGE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            CookieManager.getInstance().flush();
        }
    }

    private class Chrome extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int p) {
            progress.setProgress(p);
            progress.setVisibility(p < 100 ? View.VISIBLE : View.GONE);
        }

        /** <input type="file"> — admission documents, gallery photos. */
        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (fileCallback != null) fileCallback.onReceiveValue(null);
            fileCallback = callback;
            try {
                startActivityForResult(params.createIntent(), FILE_CHOOSER);
            } catch (ActivityNotFoundException e) {
                fileCallback = null;
                Toast.makeText(MainActivity.this, "No app to choose files.", Toast.LENGTH_SHORT).show();
                return false;
            }
            return true;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER && fileCallback != null) {
            fileCallback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultCode, data));
            fileCallback = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    // ------------------------------------------------------------------ lifecycle

    @Override
    public void onBackPressed() {
        if (web.canGoBack()) {
            web.goBack();
            return;
        }
        String url = web.getUrl();
        if (url != null && !url.startsWith(START_PAGE)) {
            // From the portal's first page, Back returns to the Student / Staff choice.
            prefs.edit().remove("start").apply();
            web.loadUrl(START_PAGE);
            return;
        }
        if (System.currentTimeMillis() - lastBack < 2000) {
            super.onBackPressed();
        } else {
            lastBack = System.currentTimeMillis();
            Toast.makeText(this, "Press back again to close", Toast.LENGTH_SHORT).show();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        web.saveState(out);
    }

    @Override
    protected void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }
}
