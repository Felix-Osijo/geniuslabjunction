(function () {
    var timer = document.getElementById('quiz-timer');
    var form = document.getElementById('challenge-form');
    if (!timer || !form) return;

    var end = Date.now() + parseInt(timer.getAttribute('data-remaining'), 10) * 1000;
    var submitted = false;

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function tick() {
        var left = Math.max(0, Math.round((end - Date.now()) / 1000));
        timer.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60);
        if (left <= 60) timer.classList.add('is-low');
        if (left === 0 && !submitted) {
            submitted = true;
            form.submit();
        }
    }

    form.addEventListener('submit', function () {
        submitted = true;
    });

    tick();
    setInterval(tick, 500);
})();
