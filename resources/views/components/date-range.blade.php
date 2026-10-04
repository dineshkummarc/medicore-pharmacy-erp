<div class="flex items-center gap-2">
    <label class="sr-only" for="flt-from">From</label>
    <input id="flt-from" type="date" name="from" value="{{ request('from') }}" class="field-input w-auto py-2" max="{{ today()->toDateString() }}">
    <span class="text-slate-400">–</span>
    <label class="sr-only" for="flt-to">To</label>
    <input id="flt-to" type="date" name="to" value="{{ request('to') }}" class="field-input w-auto py-2">
</div>
