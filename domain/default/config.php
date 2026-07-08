<?php
\wco\kernel\WCO::setCss([
    '/default/bootstrap-5.3.8/css/bootstrap.css',
    '/default/css/style.css?v=1',
]);
\wco\kernel\WCO::setJs([
    '/default/js/jquery-3.6.0.js',
    '/default/bootstrap-5.3.8/js/bootstrap.js',
    '/default/js/notify.js',
    '/default/js/vue.global.min.3.5.32.js',
    '/default/js/rooms.js'
], 'end');
