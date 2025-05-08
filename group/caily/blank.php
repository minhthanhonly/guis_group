<?php

require_once('../application/loader.php');
$view->heading('タイムカード');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-12">
				<h4 class="card-title mb-0" d><span id="timecard_title"><?= $_SESSION['realname'] ?></span><br><small
                        id="timecard_type" class="badge bg-label-info fs-6"></small></h4>
            </div>
        </div>
        <div class="card-body">

        </div>
    </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>
