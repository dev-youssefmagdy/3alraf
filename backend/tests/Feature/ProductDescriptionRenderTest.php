<?php

use App\Support\Marketing\RichText;

it('renders a plain-text description with line breaks and without double escaping', function () {
    expect(RichText::render("Line one\nTom &amp; Jerry <3"))->toBe("Line one<br />\nTom &amp; Jerry &lt;3");
});

it('renders an HTML description safely and keeps its structure', function () {
    expect(RichText::render('<b>Specs</b>'."\n".'30mm'))->toBe("<b>Specs</b><br>\n30mm")
        ->and(RichText::render('<div>One</div><div><p>Two</p></div><h1>T</h1><p>&nbsp;</p>'))->toBe('<p>One</p><p>Two</p><h2>T</h2>')
        ->and(RichText::render('<p onclick="x()">hi</p><script>alert(1)</script><img src=x onerror=alert(1)><a href="https://evil.test">e</a>'))->toBe('<p>hi</p>e');
});
