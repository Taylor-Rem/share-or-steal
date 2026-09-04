<?php

it('serves the Vue shell for every app route', function (string $path, string $app) {
    $this->get($path)->assertOk()->assertSee('data-app="'.$app.'"', false);
})->with([
    ['/', 'phone'],
    ['/play/DEMO', 'phone'],
    ['/screen/DEMO', 'screen'],
    ['/director', 'director'],
    ['/director/DEMO', 'director'],
    ['/director/DEMO/analysis', 'director'],
]);
