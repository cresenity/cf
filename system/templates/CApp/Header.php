<?php

if (!isset($title)) {
    $title = '';
}
?>
<form class="capp-header-form" id="<?php $this->element()->id() . '-form' ?>" >
    <div class = "section-header d-flex align-items-center">
        <h3><?php echo $title; ?></h3>
        <div class="ml-auto section-header-actions">
            <?php echo $this->section('actions'); ?>
        </div>
    </div>

</form>
