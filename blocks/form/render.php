<?php
/**
 * Server-rendered output for the lonsda/form block.
 *
 * Dynamic rather than static: a form's structure is edited elsewhere and must
 * not be frozen into post content at the moment it was inserted.
 *
 * @var array $attributes
 */

defined('ABSPATH') || exit;

$llf_id = isset($attributes['formId']) ? (int) $attributes['formId'] : 0;

if ($llf_id < 1) {
    return;
}

$llf_result = \LonsdaLightForm\Submission::result();
$llf_args   = ($llf_result && (int) $llf_result['form_id'] === $llf_id)
    ? ['errors' => $llf_result['errors'], 'values' => $llf_result['values'], 'notice' => $llf_result['notice']]
    : [];

$llf_markup = \LonsdaLightForm\Renderer::form($llf_id, $llf_args);

if ('' === $llf_markup) {
    return;
}

printf(
    '<div %s>%s</div>',
    get_block_wrapper_attributes(),
    $llf_markup // Already escaped field by field by the renderer.
);
