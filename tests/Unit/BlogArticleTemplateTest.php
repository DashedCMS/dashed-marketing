<?php

use Dashed\DashedMarketing\Templates\BlogArticleTemplate;

it('reports blog as content type', function () {
    $template = new BlogArticleTemplate();
    expect($template->contentType())->toBe('blog');
});

it('validates output schema matches h2_sections shape', function () {
    $template = new BlogArticleTemplate();
    $schema = $template->outputSchema();

    expect($schema)->toHaveKey('h2_sections');
});

it('only targets the Article class', function () {
    $template = new BlogArticleTemplate();

    expect($template->canTarget('Dashed\\DashedArticles\\Models\\Article'))->toBeTrue();
    expect($template->canTarget('Dashed\\DashedCore\\Models\\Page'))->toBeFalse();
});
