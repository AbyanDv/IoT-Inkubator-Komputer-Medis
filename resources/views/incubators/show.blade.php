{{-- @var \App\Models\Incubator $incubator --}}
{{-- @var bool $canManagePairing --}}
{{-- @var \Illuminate\Support\Collection<int, \App\Models\Baby> $unassignedBabies --}}
{{-- @var array $initialTelemetry --}}
{{-- @var array $history --}}
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $incubator->name }} · Incubator</title><link rel="stylesheet" href="{{ asset('build/assets/app.css') }}"><script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script><script defer src="https://cdn.jsdelivr.net/npm/chart.js@4"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900"><main class="mx-auto max-w-6xl p-4 sm:p-6 md:p-10" x-data="telemetryDashboard()" x-init="start()">
    <a href="{{ route('incubators.index') }}" class="text-sm font-semibold text-teal-700">← All incubators</a>
    <header class="my-6 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-widest text-teal-700">{{ $incubator->device_code }}</p><h1 class="mt-2 text-3xl font-bold">{{ $incubator->name }}</h1><p class="mt-2 text-slate-500">{{ $incubator->currentBaby?->name ?? 'Standby' }} · {{ ucfirst($incubator->status) }}</p><p class="mt-1 text-sm text-slate-500">Last heartbeat: {{ $incubator->last_heartbeat_at?->diffForHumans() ?? '—' }}</p></div><a class="rounded-lg border border-slate-300 bg-white px-4 py-2 font-medium" href="{{ route('incubators.config.edit', $incubator) }}">Device configuration</a></header>
    <p class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">Simulation prototype only. Not for clinical use or patient care.</p>
    @if (session('status'))<p class="mb-5 rounded-lg bg-emerald-100 p-3 text-emerald-800" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg bg-rose-100 p-3 text-rose-800">{{ $errors->first() }}</div>@endif
    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
        <section class="h-fit rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h2 class="text-lg font-bold">Baby assignment</h2>@if ($incubator->current_baby_id)<p class="mt-3 text-sm text-slate-600">Paired with <strong>{{ $incubator->currentBaby?->name }}</strong></p>@if ($canManagePairing)<form class="mt-5" method="POST" action="{{ route('incubators.unpair', $incubator) }}">@csrf<button class="w-full rounded-lg bg-rose-600 px-4 py-2 font-semibold text-white">Unpair baby</button></form>@endif @elseif ($canManagePairing)<form class="mt-4 space-y-3" method="POST" action="{{ route('incubators.pair', $incubator) }}">@csrf<label class="block text-sm font-medium" for="baby_id">Unassigned baby</label><select id="baby_id" name="baby_id" required class="w-full rounded-lg border-slate-300"><option value="">Choose baby</option>@foreach ($unassignedBabies as $baby)<option value="{{ $baby->id }}">{{ $baby->name }} ({{ $baby->baby_code }})</option>@endforeach</select><button class="w-full rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white">Pair baby</button></form>@else<p class="mt-3 text-sm text-slate-500">No baby assigned.</p>@endif</section>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div class="flex items-center justify-between"><h2 class="text-lg font-bold">Live telemetry</h2><span class="text-xs text-slate-500" x-text="updatedAt"></span></div><template x-if="!telemetry"><p class="py-12 text-center text-slate-500">Waiting for first telemetry reading.</p></template><div class="mt-5 grid grid-cols-2 gap-4 xl:grid-cols-4"><template x-for="metric in metrics" :key="metric.key"><article class="rounded-xl bg-slate-50 p-4"><p class="text-sm text-slate-500" x-text="metric.label"></p><p class="mt-2 text-2xl font-bold"><span x-text="value(metric.key)"></span><span class="ml-1 text-sm font-medium text-slate-500" x-text="metric.unit"></span></p><div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-teal-600 transition-all" :style="`width:${percentage(metric.key)}%`"></div></div></article></template></div><div class="mt-5 grid gap-3 sm:grid-cols-2"><div class="rounded-lg bg-slate-50 p-3"><h3 class="text-sm font-semibold text-slate-700">Sensor health</h3><div class="mt-2 flex flex-wrap gap-3"><div class="flex items-center gap-2"><span class="text-sm text-slate-600">DS18B20:</span><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="telemetryUnavailable ? 'bg-amber-100 text-amber-800' : sensorClass('ds18b20')" x-text="telemetryUnavailable ? 'Stale' : sensorState('ds18b20')"></span></div><div class="flex items-center gap-2"><span class="text-sm text-slate-600">DHT22:</span><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="telemetryUnavailable ? 'bg-amber-100 text-amber-800' : sensorClass('dht22')" x-text="telemetryUnavailable ? 'Stale' : sensorState('dht22')"></span></div></div></div><div class="rounded-lg bg-slate-50 p-3"><h3 class="text-sm font-semibold text-slate-700">Actuators</h3><p class="mt-2 text-sm text-slate-600">Blue LED: <span class="font-semibold" x-text="telemetryUnavailable ? 'Stale' : actuatorState('blue_led_status')"></span></p><p class="mt-1 text-sm text-slate-600">Heater PWM: <span class="font-semibold" x-text="telemetryUnavailable ? '—' : actuatorLevel('heater_pwm')"></span>% · Fan PWM: <span class="font-semibold" x-text="telemetryUnavailable ? '—' : actuatorLevel('fan_pwm')"></span>%</p></div></div></section>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2"><h2 class="text-lg font-bold">Temperature history</h2><div class="mt-4 h-56"><canvas x-ref="temperatureChart" role="img" aria-label="Temperature history"></canvas></div><p class="text-sm text-slate-500">Latest <span x-text="countHistory"></span> stored readings · °C</p></section>
    </div>
</main><script>
function telemetryDashboard() {
    return {
        telemetry: @json($initialTelemetry['telemetry']),
        history: @json($history),
        updatedAt: 'Connecting…',
        sensorStatus: @json($initialTelemetry['telemetry']['sensor_status'] ?? null),
        telemetryUnavailable: false,
        metrics: [{key:'temperature_object',label:'Object temperature',unit:'°C'},{key:'humidity',label:'Humidity',unit:'%'},{key:'heater_pwm',label:'Heater PWM',unit:'%'},{key:'fan_pwm',label:'Fan PWM',unit:'%'}],
        get countHistory() { return this.history.length; },
        chart: null,
        updateChart() {
            if (typeof Chart === 'undefined' || !this.$refs.temperatureChart) return;
            if (!this.chart) {
                this.chart = new Chart(this.$refs.temperatureChart, {type:'line',data:{labels:[],datasets:[{label:'Object temperature (°C)',data:[],borderColor:'#0f766e',tension:0.25}]},options:{responsive:true,maintainAspectRatio:false,animation:false}});
            }
            if (this.chart) {
                this.chart.data.labels = this.history.map(point => point.time);
                this.chart.data.datasets[0].data = this.history.map(point => point.temperature);
                this.chart.update();
            }
        },
        sensorState(key) { return this.sensorStatus?.[key] === true ? 'OK' : (this.sensorStatus?.[key] === false ? 'FAULT' : 'Unavailable'); },
        actuatorState(key) { return this.telemetry?.[key] == null ? 'Unavailable' : (this.telemetry[key] ? 'On' : 'Off'); },
        actuatorLevel(key) { return this.telemetry?.[key] ?? '—'; },
        sensorClass(key) { return this.sensorStatus?.[key] === true ? 'bg-emerald-100 text-emerald-800' : (this.sensorStatus?.[key] === false ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'); },
        value(key) { return this.telemetry?.[key] ?? '—'; },
        percentage(key) { const number = Number(this.telemetry?.[key] ?? 0); return Math.max(0, Math.min(100, number)); },
        async poll() {
            try {
                const response = await fetch(@js(route('api.incubators.live-telemetry', $incubator)), {headers:{Accept:'application/json'}, credentials:'same-origin'});
                if (!response.ok) throw new Error('Telemetry request failed');
                const data = await response.json();
                this.telemetryUnavailable = false;
                this.telemetry = data.telemetry;
                this.sensorStatus = data.telemetry?.sensor_status ?? null;
                const recordedAt = data.telemetry?.recorded_at;
                if (data.telemetry?.temperature_object != null && recordedAt && !this.history.some(point => point.time === recordedAt)) {
                    this.history.push({temperature:Number(data.telemetry.temperature_object),time:recordedAt});
                    this.history = this.history.slice(-20);
                }
                this.updateChart();
                this.updatedAt = new Date().toLocaleTimeString();
            } catch { this.telemetryUnavailable = true; this.updatedAt = 'Telemetry unavailable'; }
        },
        start() { this.updatedAt = new Date().toLocaleTimeString(); this.updateChart(); this.poll(); setInterval(() => this.poll(), 2500); }
    };
}
</script></body></html>
