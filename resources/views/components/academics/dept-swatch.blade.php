{{-- Department colour chip used by the timetable and subject lists. --}}
@props(['code' => null, 'small' => false])
@php $c = \App\Http\Controllers\Academics\TimetableController::deptColor($code); @endphp
<span {{ $attributes->merge(['class' => 'inline-block shrink-0 rounded-[4px]']) }}
      style="width: {{ $small ? 8 : 10 }}px; height: {{ $small ? 8 : 28 }}px; background: {{ $c }}"></span>
