<?php
require("../template/top.php");
head("Add a Movie", true, true, array("Home" => "/", "Movies" => "/movie/browse", "Add" => "/movie/add"));
?>
<style>
	.wizard-title {
		text-align: center;
		margin-top: -15px;
	}
	.hr-or {
		font-size: 18px;
		width: 100%;
		height: 30px;
		text-align: center;
		margin-bottom: 10px;
	}
	.hr-div {
		width: 50px;
		height: 7px;
		border-top: 5px dotted #bcbcbc;
		display: inline-block;
	}
	.hr-div.left {
		margin-right: 10px;
	}
	.hr-div.right {
		margin-left: 10px;
	}
	#search-results-spinner {
		text-align:  center;
		line-height: 50px;
	}
	#search-results-spinner > img {
		width: 50px;
		height: 50px;
	}
	.sr-list-left {
		display: inline-block;
		width: 20%;
		padding-right: 10px;
	}
	.sr-list-left img {
		width: 100%;
		padding: 2px;
		border-radius: 5px;
		background-color: whitesmoke;
		border: 1px solid #d8d8d8;
	}
	.sr-list-right {
		display: inline-block;
		width: 80%;
		vertical-align: top;
		padding-top: 5px;
	}
	#search-results-list li:not(:last-of-type) {
		border-bottom: 3px solid whitesmoke;
	}
	vr {
		width: 1px;
		border: 0.5px solid #cacaca;
		margin: 5px;
	}
	#title_desc {
		margin-top: 2px;
		border-top: 1px dotted #82828273;
		padding-top: 8px;
	}
	#title_year {
		font-size: 15px;
	}
	.movie-title-add-link {
		font-size: 18px;
	}
</style>
<div id="page-content">
	<div class="row">
		<div class="col-lg-12">

			<div class="panel">

				<!-- Form wizard with Validation -->
				<!--===================================================-->
				<div id="add-form-bv-wz">
					<div class="wz-heading pad-top">

						<!--Nav-->
						<ul class="row wz-step wz-icon-bw wz-nav-off mar-top wz-steps">
							<li class="col-xs-3 active">
								<a data-toggle="tab" href="#add-mv-tab1" aria-expanded="true">
									<span class="text-danger"><i class="fa fa-search icon-2x"></i></span>
									<p class="text-semibold mar-no">Find</p>
								</a>
							</li>
							<li class="col-xs-3">
								<a data-toggle="tab" href="#add-mv-tab2">
									<span class="text-warning"><i class="fa fa-cloud-download icon-2x"></i></span>
									<p class="text-semibold mar-no">Download</p>
								</a>
							</li>
							<li class="col-xs-3">
								<a data-toggle="tab" href="#add-mv-tab3">
									<span class="text-info"><i class="fa fa-magic icon-2x"></i></span>
									<p class="text-semibold mar-no">Process</p>
								</a>
							</li>
							<li class="col-xs-3">
								<a data-toggle="tab" href="#add-mv-tab4">
									<span class="text-success"><i class="fa fa-check icon-2x"></i></span>
									<p class="text-semibold mar-no">Done</p>
								</a>
							</li>
						</ul>
					</div>

					<!--progress bar-->
					<div class="progress progress-xs">
						<div class="progress-bar progress-bar-primary" style="width: 25%; left: 0%; position: relative; transition: all 0.5s;"></div>
					</div>


					<!--Form-->
					<form id="add-form-bv-wz-form" class="form-horizontal bv-form" novalidate="novalidate"><button type="submit" class="bv-hidden-submit" style="display: none; width: 0px; height: 0px;" disabled="disabled"></button>
						<div class="panel-body">
							<div class="tab-content">

								<!--First tab-->
								<div id="add-mv-tab1" class="tab-pane active in">
									<h3 class="wizard-title">Movie search</h3>
									<hr/>
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">Movie title search</label>
										<div class="col-lg-7">
											<input type="text" id="title-search" class="form-control" name="title-search" placeholder="e.g. Terminator">
										</div>
									</div>
									<div class="hr-or"><div class="hr-div left"></div>or<div class="hr-div right"></div></div>
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">IMDB ID search</label>
										<div class="col-lg-7">
											<input type="text" id="imdbid-search" class="form-control" name="imdbid-search" placeholder="e.g. tt1234567">
										</div>
									</div>
									
									<div class="col-lg-offset-3 col-lg-7"><button id="search-for-movie-btn" type="button" class="btn btn-primary">Search</button></div>
								</div>

								<!--Second tab-->
								<div id="add-mv-tab2" class="tab-pane fade">
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">First name</label>
										<div class="col-lg-7">
											<input type="text" placeholder="First name" name="firstName" class="form-control" data-bv-field="firstName"><i class="form-control-feedback" data-bv-icon-for="firstName" style="display: none;"></i>
										<small class="help-block" data-bv-validator="notEmpty" data-bv-for="firstName" data-bv-result="NOT_VALIDATED" style="display: none;">The first name is required and cannot be empty</small><small class="help-block" data-bv-validator="regexp" data-bv-for="firstName" data-bv-result="NOT_VALIDATED" style="display: none;">The first name can only consist of alphabetical characters and spaces</small></div>
									</div>
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">Last name</label>
										<div class="col-lg-7">
											<input type="text" placeholder="Last name" name="lastName" class="form-control" data-bv-field="lastName"><i class="form-control-feedback" data-bv-icon-for="lastName" style="display: none;"></i>
										<small class="help-block" data-bv-validator="notEmpty" data-bv-for="lastName" data-bv-result="NOT_VALIDATED" style="display: none;">The last name is required and cannot be empty</small><small class="help-block" data-bv-validator="regexp" data-bv-for="lastName" data-bv-result="NOT_VALIDATED" style="display: none;">The last name can only consist of alphabetical characters and spaces</small></div>
									</div>
								</div>

								<!--Third tab-->
								<div id="add-mv-tab3" class="tab-pane">
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">Phone Number</label>
										<div class="col-lg-7">
											<input type="text" placeholder="Phone number" name="phoneNumber" class="form-control" data-bv-field="phoneNumber"><i class="form-control-feedback" data-bv-icon-for="phoneNumber" style="display: none;"></i>
										<small class="help-block" data-bv-validator="notEmpty" data-bv-for="phoneNumber" data-bv-result="NOT_VALIDATED" style="display: none;">The phone number is required and cannot be empty</small><small class="help-block" data-bv-validator="digits" data-bv-for="phoneNumber" data-bv-result="NOT_VALIDATED" style="display: none;">The value can contain only digits</small></div>
									</div>
									<div class="form-group has-feedback">
										<label class="col-lg-3 control-label">Address</label>
										<div class="col-lg-7">
											<input type="text" placeholder="Phone number" name="address" class="form-control" data-bv-field="address"><i class="form-control-feedback" data-bv-icon-for="address" style="display: none;"></i>
										<small class="help-block" data-bv-validator="notEmpty" data-bv-for="address" data-bv-result="NOT_VALIDATED" style="display: none;">The address is required</small></div>
									</div>
								</div>

								<!--Fourth tab-->
								<div id="add-mv-tab4" class="tab-pane  mar-btm text-center">
									<h4>Thank you</h4>
									<p class="text-muted">Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. </p>
								</div>
							</div>
						</div>

						<!--Footer button-->
						<div class="panel-footer text-right" style="display: none;">
							<div class="box-inline">
								<button type="button" class="previous btn btn-primary disabled">Previous</button>
								<button type="button" class="next btn btn-primary">Next</button>
								<button type="button" class="finish btn btn-warning" disabled="" style="display: none;">Finish</button>
							</div>
						</div>
					</form>
				</div>
				<!--===================================================-->
				<!-- End Form wizard with Validation -->

			</div>
		</div>
	</div>

	<div id="search-results-spinner" style="display: none;">
		<img src="/assets/x-editable/img/loading.gif"/>
	</div>

	<div class="panel" id="search-results-panel" style="display: none;">
	        <div class="panel-body">
			<div class="pad-hor mar-top">
				<h2 class="text-thin mar-no"><span id="search_result_total"></span> results found for: <i class="text-info text-normal" id="search_results_query"></i></h2>
			</div>
			<hr>
			<ul id="search-results-list" class="list-group bord-no">
			</ul>
		</div>
	</div>

</div>
<?php
footer(false);
?>
<script src="/js/movie/add.js"></script>
<script>
$(document).ready(function() {
	window["nav_loaded_js_movie_add_js"]();
});
</script>
