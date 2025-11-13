<?php
if (!defined('ABSPATH')) exit;
$user = ScripGrab\Auth::current_user();
?>
<div class="sg-wrap">
  <?php include SG_PATH . 'templates/partials/profile-card.php'; ?>

  <h1 class="sg-heading">ScrinGrab</h1>

  <nav class="sg-tabs" data-sg-tabs>
    <button class="sg-tab active" data-tab="capture">Backup / Capture</button>
    <button class="sg-tab" data-tab="settings">Settings</button>
    <button class="sg-tab" data-tab="remote">Remote Control</button>
  </nav>

  <div class="sg-tab-panels">
    <div class="sg-tab-panel active" data-tab-panel="capture">
      <?php include SG_PATH . 'templates/partials/capture-tab.php'; ?>
    </div>
    <div class="sg-tab-panel" data-tab-panel="settings">
      <?php include SG_PATH . 'templates/partials/settings-tab.php'; ?>
    </div>
    <div class="sg-tab-panel" data-tab-panel="remote">
      <?php include SG_PATH . 'templates/partials/remote-tab.php'; ?>
    </div>
  </div>
</div>
