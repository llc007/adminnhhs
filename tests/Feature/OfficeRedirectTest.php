<?php

test('redirects to the Google Drive Office installer', function () {
    $response = $this->get('/office');

    $response->assertRedirect('https://drive.google.com/file/d/1i8T9g1mlSsUj4xhGGC6-Y99Fwe30fMy7/view?usp=sharing');
});
