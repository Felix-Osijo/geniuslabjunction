(function () {
    var button = document.getElementById('add-question');
    var holder = document.getElementById('more-questions');
    if (!button || !holder || !window.QUIZ) return;

    var next = window.QUIZ.next;

    function field(i, key, label, max) {
        return '<div class="field"><label for="q' + i + '-' + key + '">' + label + '</label>' +
            '<input type="text" id="q' + i + '-' + key + '" name="q[' + i + '][' + key + ']" maxlength="' + max + '"></div>';
    }

    button.addEventListener('click', function () {
        if (next >= window.QUIZ.max) {
            button.disabled = true;
            return;
        }

        var i = next++;
        var block = document.createElement('fieldset');
        block.className = 'q-block';
        block.innerHTML =
            '<legend>Question ' + (i + 1) + '</legend>' +
            '<input type="hidden" name="q[' + i + '][id]" value="0">' +
            field(i, 'question', 'Question', 500) +
            '<div class="field-row">' +
                field(i, 'a', 'Option A', 255) + field(i, 'b', 'Option B', 255) +
                field(i, 'c', 'Option C', 255) + field(i, 'd', 'Option D', 255) +
            '</div>' +
            '<div class="field-row">' +
                '<div class="field"><label for="q' + i + '-correct">Correct answer</label>' +
                '<select id="q' + i + '-correct" name="q[' + i + '][correct]">' +
                '<option>A</option><option>B</option><option>C</option><option>D</option></select></div>' +
                field(i, 'explanation', 'Explanation (shown after the quiz)', 500) +
            '</div>';

        holder.appendChild(block);
        block.querySelector('input[type="text"]').focus();

        if (next >= window.QUIZ.max) button.disabled = true;
    });
})();

(function () {
    var bar = document.querySelector('.format-bar');
    var area = document.getElementById('body');
    if (!bar || !area) return;

    bar.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) return;

        var start = area.selectionStart;
        var end = area.selectionEnd;
        var value = area.value;
        var selected = value.slice(start, end);
        var wrap = button.getAttribute('data-wrap');
        var line = button.getAttribute('data-line');

        if (wrap) {
            var parts = wrap.split('|');
            area.value = value.slice(0, start) + parts[0] + selected + parts[1] + value.slice(end);
            var cursor = selected ? end + parts[0].length + parts[1].length : start + parts[0].length;
            area.setSelectionRange(cursor, cursor);
        } else if (line) {
            var lineStart = value.lastIndexOf('\n', start - 1) + 1;
            area.value = value.slice(0, lineStart) + line + value.slice(lineStart);
            area.setSelectionRange(start + line.length, end + line.length);
        }

        area.focus();
    });
})();
