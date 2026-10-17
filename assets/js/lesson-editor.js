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
