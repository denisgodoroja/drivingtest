(function ($) {
    'use strict';

    var global_timer = time();
    var time_left = 0;

    function time() {
        return Math.floor(new Date().getTime() / 1000);
    }

    function pad(num, size) {
        var s = "000000000" + num;
        return s.substr(s.length - size);
    }

    function updateTimer() {
        var timer = time() - global_timer;
        timer = time_left - timer;
        if (timer <= 0) {
            timer = 0;
            //location.reload(true);
        }
        var minutes = pad(Math.floor(timer / 60), 2);
        var seconds = pad(timer % 60, 2);

        $('.timer .clock').text(minutes + ':' + seconds);

        setTimeout(updateTimer, 200);
    }

    if ($('.timer .clock').length) {
        time_left = parseInt($('.timer .clock').attr('seconds'));
        if (isNaN(time_left)) {
            time_left = 0;
        }
        updateTimer();
    }
}(jQuery));
