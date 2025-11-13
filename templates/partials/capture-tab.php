<?php
if (!defined('ABSPATH')) exit;

$pages = get_pages([
  'sort_order'  => 'ASC',
  'sort_column' => 'post_title',
  'post_status' => ['publish'],
]);
$capture_selection = \ScripGrab\Admin::capture_selection_map();
?>

<div class="sg-panel" data-sg-context="capture">
  <div class="sg-panel-left">
    <div class="sg-panel-header">
      <h2>Pages</h2>
      <p class="sg-small">Choose the pages you want to capture for the selected device.</p>
    </div>

    <div class="sg-devices" data-sg-device-group>
      <label><input type="radio" name="sg-device" value="desktop" checked> Desktop</label>
      <label><input type="radio" name="sg-device" value="tablet"> Tablet</label>
      <label><input type="radio" name="sg-device" value="mobile"> Mobile</label>
    </div>

    <div class="sg-page-actions">
      <label class="sg-select-all">
        <input type="checkbox" data-sg-select-all="capture">
        Select all pages
      </label>
    </div>

    <ul class="sg-page-list" data-sg-page-list>
      <?php foreach ($pages as $p):
        $raw_url = get_permalink($p->ID);
        $normalized_url = esc_url_raw($raw_url);
      ?>
        <li>
          <label>
            <input
              type="checkbox"
              value="<?php echo esc_url($raw_url); ?>"
              class="sg-page-checkbox"
              data-title="<?php echo esc_attr($p->post_title); ?>"
              data-url="<?php echo esc_attr($normalized_url); ?>"
              <?php checked(in_array($normalized_url, $capture_selection['desktop'] ?? [], true)); ?>
            >
            <?php echo esc_html($p->post_title); ?>
          </label>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="sg-panel-right">
    <div class="sg-preview-header">
      <h2>Preview</h2>
      <p class="sg-small">Select a page on the left to see the latest capture or placeholder.</p>
    </div>

    <div
      class="sg-preview-box"
      data-sg-preview
      data-sg-preview-context="capture"
      data-sg-preview-device="dynamic"
    >
      <div class="sg-preview-placeholder" data-sg-preview-placeholder>
        <strong>No page selected</strong>
        <p class="sg-small">Pick a page from the list to load a preview automatically.</p>
      </div>
      <img src="" alt="" class="sg-preview-img" data-sg-preview-img>
    </div>

    <div class="sg-panel-actions">
      <p class="sg-small sg-panel-note">Exports include screenshots that have already been captured. Use Capture Now to refresh them before exporting.</p>
      <button type="button" class="button button-primary" data-sg-capture-now>Capture Now</button>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sg-export-form">
        <?php wp_nonce_field('sg_export'); ?>
        <input type="hidden" name="action" value="sg_export">
        <button type="submit" class="button button-secondary">Export</button>
      </form>
    </div>
  </div>
</div>
