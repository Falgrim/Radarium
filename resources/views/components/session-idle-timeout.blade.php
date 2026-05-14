@auth
@php
    $idleMinutes = max(1, (int) config('session.lifetime', 30));
@endphp
<script>
(function () {
    var limitMs = {{ $idleMinutes }} * 60 * 1000;
    var targetUrl = @json(route('index', ['idle_timeout' => 1]));
    var t;
    function go() {
        window.location.assign(targetUrl);
    }
    function arm() {
        clearTimeout(t);
        t = setTimeout(go, limitMs);
    }
    ['load', 'click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (ev) {
        document.addEventListener(ev, arm, { passive: true });
    });
    arm();
})();
</script>
@endauth
