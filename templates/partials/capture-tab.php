<?php if (!defined('ABSPATH')) exit; ?>

<div class="sg-panel">
  <div class="sg-panel-left">
    <h2>Pages</h2>
    <p class="sg-small">Select pages to capture for this device.</p>

    <div class="sg-devices">
      <label><input type="radio" name="sg-device" value="desktop" checked> Desktop</label>
      <label><input type="radio" name="sg-device" value="tablet"> Tablet</label>
      <label><input type="radio" name="sg-device" value="mobile"> Mobile</label>
    </div>

    <ul class="sg-page-list" data-sg-page-list>
      <?php
      $pages = get_pages();
      foreach ($pages as $p): ?>
        <li>
          <label>
            <input type="checkbox" value="<?php echo esc_url(get_permalink($p->ID)); ?>" class="sg-page-checkbox">
            <?php echo esc_html($p->post_title); ?>
          </label>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="sg-panel-right">
    <h2>Preview</h2>
    <div class="sg-preview-box">
      <p class="sg-small">Select a page to see a preview here.</p>
      <img src="" alt="" class="sg-preview-img" style="display:none;">
    </div>
  </div>
</div>
