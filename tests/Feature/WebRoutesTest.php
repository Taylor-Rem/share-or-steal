<?php

it('serves the Vue shell for every app route', function (string $path, string $app) {
    // CI runs Pest without a Vite build; the shell's @vite must not need the manifest here.
    $this->withoutVite()->get($path)->assertOk()->assertSee('data-app="'.$app.'"', false);
})->with([
    ['/', 'phone'],
    ['/play/DEMO', 'phone'],
    ['/screen/DEMO', 'screen'],
    ['/director', 'director'],
    ['/director/DEMO', 'director'],
    ['/director/DEMO/analysis', 'director'],
]);
