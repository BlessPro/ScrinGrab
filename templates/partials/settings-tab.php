<?php
if (!defined('ABSPATH')) exit;

$settings = get_option('sg_settings', [
  'retention' => 3,
  'frequency' => 'weekly',
  'storage'   => 'local',
]);

if (!is_array($settings)) {
  $settings = [];
}
$settings = wp_parse_args($settings, [
  'retention' => 3,
  'frequency' => 'weekly',
  'storage'   => 'local',
]);

$screenshot_key = get_option('sg_screenshot_key', '');
$google_client_id = get_option('sg_google_client_id', '');
$google_client_secret = get_option('sg_google_client_secret', '');

$schedule_pages = get_option('sg_schedule_pages', []);
if (!is_array($schedule_pages)) {
  $schedule_pages = [];
}

$all_pages = get_pages([
  'sort_order'  => 'ASC',
  'sort_column' => 'post_title',
  'post_status' => ['publish'],
]);
?>

<div class="sg-panel" data-sg-context="settings">
  <div class="sg-panel-left">
    <div class="sg-panel-header">
      <h2>Backup Settings</h2>
      <p class="sg-small">Control how often ScrinGrab runs and how many copies to keep.</p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sg-settings-form">
      <?php wp_nonce_field('sg_save_settings'); ?>
      <input type="hidden" name="action" value="sg_save_settings">

      <label class="sg-field">
        <span class="sg-field-label">Retention</span>
        <select name="retention">
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?php echo esc_attr($i); ?>" <?php selected((int) $settings['retention'] === $i); ?>>Keep <?php echo esc_html($i); ?> <?php echo $i === 1 ? 'copy' : 'copies'; ?></option>
          <?php endfor; ?>
        </select>
      </label>

      <label class="sg-field">
        <span class="sg-field-label">Frequency</span>
        <select name="frequency">
          <?php
          $frequencies = [
            'manual'  => 'Manual',
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
          ];
          foreach ($frequencies as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['frequency'], $value); ?>>
              <?php echo esc_html($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="sg-field">
        <span class="sg-field-label">Storage</span>
        <select name="storage">
          <option value="local" <?php selected($settings['storage'], 'local'); ?>>Local (this site)</option>
          <option value="drive" disabled <?php selected($settings['storage'], 'drive'); ?>>Google Drive (coming soon)</option>
        </select>
      </label>

      <label class="sg-field">
        <span class="sg-field-label">ScreenshotMachine API Key</span>
        <input type="text" name="screenshot_key" value="<?php echo esc_attr($screenshot_key); ?>" placeholder="Enter API key">
        <p class="sg-small">Used for saving screenshots to Media and exports. Previews use a free provider.</p>
      </label>
      <label class="sg-field">
        <span class="sg-field-label">Google Client ID</span>
        <input type="text" name="google_client_id" value="<?php echo esc_attr($google_client_id); ?>" placeholder="your-client-id.apps.googleusercontent.com">
      </label>
      <label class="sg-field">
        <span class="sg-field-label">Google Client Secret</span>
        <input type="text" name="google_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" placeholder="Client secret">
        <p class="sg-small">Redirect URI: <code><?php echo esc_html( admin_url('admin-post.php?action=sg_oauth_callback') ); ?></code></p>
      </label>

      <div class="sg-field">
        <button type="submit" class="button button-primary">Save Settings</button>
      </div>
    </form>
  </div>

  <div class="sg-panel-right">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sg-schedule-form">
      <?php wp_nonce_field('sg_save_schedule'); ?>
      <input type="hidden" name="action" value="sg_save_schedule">

      <div class="sg-panel-header">
        <h2>Scheduled Pages</h2>
        <p class="sg-small">Pick the desktop pages to include whenever ScrinGrab runs automatically.</p>
      </div>

      <div class="sg-page-actions">
        <label class="sg-select-all">
          <input type="checkbox" data-sg-select-all="settings">
          Select all pages
        </label>
      </div>

      <ul class="sg-page-list" data-sg-page-list>
        <?php foreach ($all_pages as $page):
          $raw_url = get_permalink($page->ID);
          $normalized_url = esc_url_raw($raw_url);
        ?>
          <li>
            <label>
              <input
                type="checkbox"
                name="pages[]"
                value="<?php echo esc_url($raw_url); ?>"
                class="sg-page-checkbox"
                data-title="<?php echo esc_attr($page->post_title); ?>"
                data-url="<?php echo esc_attr($normalized_url); ?>"
                <?php checked(in_array($normalized_url, $schedule_pages, true)); ?>
              >
              <?php echo esc_html($page->post_title); ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="sg-preview-header">
        <h2>Preview</h2>
        <p class="sg-small">Desktop captures preview here as soon as you select a page.</p>
      </div>

      <div
        class="sg-preview-box"
        data-sg-preview
        data-sg-preview-context="settings"
        data-sg-preview-device="desktop"
      >
        <div class="sg-preview-placeholder" data-sg-preview-placeholder>
          <strong>No page selected</strong>
          <p class="sg-small">Choose a page to preview the desktop capture.</p>
        </div>
        <img src="" alt="" class="sg-preview-img" data-sg-preview-img>
      </div>

      <div class="sg-panel-actions">
        <button type="submit" class="button button-primary">Save Selection</button>
      </div>
    </form>
  </div>
</div>


