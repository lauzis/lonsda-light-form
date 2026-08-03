<?php
/**
 * Front-end form markup.
 *
 * @var array  $form
 * @var array  $fields
 * @var array  $values
 * @var array  $errors
 * @var string $notice
 */

defined('ABSPATH') || exit;

$llf_id        = (int) $form['id'];
$llf_recaptcha = !empty($form['settings']['recaptcha']);
?>
<form class="llf-form" method="post" action="">
    <?php if ('' !== $notice) : ?>
        <div class="llf-notice"><?php echo esc_html($notice); ?></div>
    <?php endif; ?>

    <?php wp_nonce_field('llf_submit_' . $llf_id, 'llf_nonce'); ?>
    <input type="hidden" name="<?php echo esc_attr(\LonsdaLightForm\Renderer::FIELD_FORM_ID); ?>" value="<?php echo esc_attr($llf_id); ?>">
    <input type="hidden" name="<?php echo esc_attr(\LonsdaLightForm\Renderer::FIELD_STARTED); ?>" value="<?php echo esc_attr(time()); ?>">

    <?php
    // Honeypot: hidden from people, plausible to a bot. Kept out of the
    // accessibility tree as well as out of sight, so a screen reader does not
    // announce a field its user must leave alone.
    ?>
    <div class="llf-hp" aria-hidden="true" style="position:absolute;left:-9999px;" tabindex="-1">
        <label for="llf-hp-<?php echo esc_attr($llf_id); ?>"><?php esc_html_e('Leave this field empty', 'lonsda-light-form'); ?></label>
        <input type="text" id="llf-hp-<?php echo esc_attr($llf_id); ?>"
               name="<?php echo esc_attr(\LonsdaLightForm\Renderer::FIELD_HONEYPOT); ?>"
               value="" autocomplete="off" tabindex="-1">
    </div>

    <?php foreach ($fields as $llf_field) : ?>
        <?php
        $llf_name = $llf_field['name'];
        echo \LonsdaLightForm\Renderer::field(
            $llf_field,
            array_key_exists($llf_name, $values) ? $values[$llf_name] : null,
            (string) ($errors[$llf_name] ?? '')
        );
        ?>
    <?php endforeach; ?>

    <?php if ($llf_recaptcha) : ?>
        <div class="llf-field llf-field--recaptcha">
            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(\LonsdaLightForm\Settings::get('recaptcha_site_key', '')); ?>"></div>
            <?php if (!empty($errors['_recaptcha'])) : ?>
                <span class="llf-error"><?php echo esc_html($errors['_recaptcha']); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="llf-submit">
        <button type="submit"><?php esc_html_e('Send', 'lonsda-light-form'); ?></button>
    </p>
</form>
