<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<title>SMART TRAVEL ADMIN</title>

	<!--   -->
	<link rel="shortcut icon" href="../image/favicon.ico">
	<link rel="stylesheet" href="../css/a_reset.css">
	<link rel="stylesheet" href="../css/a_variables.css">
	<link rel="stylesheet" href="../css/a_components.css">
	<link rel="stylesheet" href="../css/a_contents.css">
</head>

<body>

	<!-- header   -->
	<header class="layout-header"></header>

	<!--   -->
	<main class="layout-main">
		<!-- nav   -->
		<nav class="layout-nav"></nav>

		<section class="layout-content">
			<div class="page-toolbar">
				<a href="reservation-list.html" class="jw-button jw-button-back" aria-label=" ">
					<img src="../image/arrow4.svg" alt="">
					<span data-lan-eng="Return to list">Return to list</span>
				</a>

				<div class="page-toolbar-actions">
					<select class="select" disabled>
						<option value="contract" selected data-lan-eng="Check remaining balance">  </option>
						<option value="review" data-lan-eng="Under review"></option>
						<option value="paused" data-lan-eng="Paused"></option>
						<option value="terminated" data-lan-eng="Terminated"></option>
					</select>
   

					<button type="button" class="jw-button typeB" data-lan-eng="Save"></button>
				</div>
			</div>

			<h1 class="page-title" data-lan-eng="Reservation Details"> </h1>
			
			<div class="tab-wrap jw-mgt32">
				<p data-lan-eng="Product and Reservation Information">   </p>
				<a href="../common/reservation-location.html" target="_blank" id="meetingLocationLink"><span data-lan-eng="Meeting location"> </span><img src="../image/linkout.svg" alt=""></a>
				<a href="../common/reservation-notices.html" target="_blank" id="announcementsLink"><span data-lan-eng="Announcements"></span><img src="../image/linkout.svg" alt=""></a>
			</div>

			<h2 class="section-title jw-mgt32" data-lan-eng="Product Information"> </h2>
			<div class="card-panel jw-mgt16">
				<div class="grid-wrap">

					<div class="grid-item col-span-3">
						<label class="label-name" for="product_name" data-lan-eng="Product Name"></label>
						<input id="product_name" type="text" class="form-control"
							value="Seoul Cherry Blossom Highlights 6-Day, 5-Night Package – Includes Full Itinerary Guide and Meals, with Visits to Nami Island, Seokchon Lake, and Yunjung-ro" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="trip_range" data-lan-eng="Travel period"> </label>
						<input id="trip_range" type="text" class="form-control" value="2025-04-19 - 2025-04-24" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="meet_time" data-lan-eng="Meeting time"> </label>
						<input id="meet_time" type="text" class="form-control" value="2025-04-19 09:00" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="meet_place" data-lan-eng="Meeting place"> </label>
						<input id="meet_place" type="text" class="form-control" value="Incheon International Airport Terminal 2" disabled>
					</div>

				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Reservation Information"> </h2>
			<div class="card-panel jw-mgt16">
				<div class="grid-wrap">

					<div class="grid-item">
						<label class="label-name" for="res_no" data-lan-eng="Reservation number"> </label>
						<input id="res_no" type="text" class="form-control" value="23490871349" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="res_datetime" data-lan-eng="Reservation date and time"></label>
						<input id="res_datetime" type="text" class="form-control" value="2025-12-01 12:12" disabled>
					</div>

					<div class="grid-item"></div>

					<div class="grid-item">
						<label class="label-name" for="res_people" data-lan-eng="Number of people for reservation"> </label>
						<input id="res_people" type="text" class="form-control" value="x1, x2" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="room_opt" data-lan-eng="Room options"> </label>
						<input id="room_opt" type="text" class="form-control" value="x1, x1" disabled>
					</div>

					<div class="grid-item"></div>

					<div class="grid-item">
						<label class="label-name" for="opt_baggage" data-lan-eng="Add Cabin Baggage">  </label>
						<select id="opt_baggage" class="select" disabled>
							<option selected data-lan-eng="Not selected"></option>
							<option data-lan-eng="Add 15kg">15kg </option>
							<option data-lan-eng="Add 20kg">20kg </option>
							<option data-lan-eng="Add 30kg">30kg </option>
						</select>
					</div>

					<div class="grid-item">
						<label class="label-name" for="opt_breakfast" data-lan-eng="Breakfast Request"> </label>
						<select id="opt_breakfast" class="select" disabled>
							<option selected data-lan-eng="Apply"></option>
							<option data-lan-eng="No request"></option>
						</select>
					</div>

					<div class="grid-item">
						<label class="label-name" for="opt_wifi" data-lan-eng="WiFi Rental"> </label>
						<select id="opt_wifi" class="select" disabled>
							<option selected data-lan-eng="Apply"></option>
							<option data-lan-eng="No request"></option>
						</select>
					</div>

					<div class="grid-item col-span-3">
						<label class="label-name" for="seat_req" data-lan-eng="Airline seat request details">  </label>
						<div class="editor-box-wrap">
							<textarea id="seat_req" rows="6" placeholder="Please give me a window seat~" style="resize:none;" disabled readonly></textarea>
						</div>
					</div>

					<div class="grid-item col-span-3">
						<label class="label-name" for="etc_req" data-lan-eng="Other requests"> </label>
						<div class="editor-box-wrap">
							<textarea id="etc_req" rows="6" placeholder="Please provide us with a quiet room as it's an anniversary trip." style="resize:none;" disabled readonly></textarea>
						</div>
					</div>

				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Payment Information"> </h2>
			<div class="card-panel jw-mgt16">
				<div class="grid-wrap">

					<div class="grid-item">
						<label class="label-name" for="order_amount" data-lan-eng="Order Amount (₱)">  (₱)</label>
						<input id="order_amount" type="text" class="form-control" value="15,000" disabled>
					</div>
					
					<div class="grid-item">
						<label class="label-name" for="deposit_due" data-lan-eng="Deadline for advance payment">  </label>
						<input id="deposit_due" type="text" class="form-control" value="2025-12-01" disabled>
					</div>
					
					<div class="grid-item file-field">
						<label class="label-name"><span data-lan-eng="Advance payment proof file">  </span></label>
						<div class="cell" id="deposit_proof_container">
							<div class="field-row">
								<div class="file-display">
									<img src="../image/file.svg" alt="">
									<span id="deposit_proof_name" data-lan-eng="No file uploaded">  .</span>
								</div>
								<i></i>
								<div class="file-actions">
									<button type="button" class="btn-icon file" id="deposit_proof_download" disabled>
										<img src="../image/buttun-download.svg" alt="">
									</button>
									<button type="button" class="btn-icon file" id="deposit_proof_remove" aria-label="" disabled>
										<img src="../image/button-close2.svg" alt="">
									</button>
								</div>
							</div>
						</div>
					</div>

					<div class="grid-item">
						<label class="label-name" for="deposit_amount" data-lan-eng="Advance payment (₱)"> (₱)</label>
						<input id="deposit_amount" type="text" class="form-control" value="10,000" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="balance_amount" data-lan-eng="Balance (₱)"> (₱)</label>
						<input id="balance_amount" type="text" class="form-control" value="5,000" disabled>
					</div>
					<div class="grid-item hidden">
						<label class="label-name" for="balance_due" data-lan-eng="Payment deadline">  </label>
						<input id="balance_due" type="text" class="form-control" value="2025-12-31" disabled>
					</div>
					<div class="grid-item hidden">
						<label class="label-name"><span data-lan-eng="Proof of balance file">  </span><span class="req">*</span></label>
						<div class="cell">
							<div class="field-row jw-center">
								<div class="jw-center jw-gap10"><img src="../image/file.svg" alt=""> <span data-lan-eng="Advance payment proof file">  </span> [pdf, 328KB]</div>
								<div class="jw-center jw-gap10">
									<i></i>
									<button type="button" class="jw-button typeF"><img src="../image/buttun-download.svg" alt=""></button>
									<button type="button" class="btn-icon" aria-label=""><img src="../image/button-close2.svg" alt=""></button>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Agent Notes"> </h2>
			<div class="card-panel jw-mgt16">
				<div class="grid-wrap">
					<div class="grid-item col-span-3">
						<label class="label-name" for="agent_memo" data-lan-eng="Note"></label>
						<div class="editor-box-wrap">
							<textarea id="agent_memo" rows="8" placeholder="  " style="resize:none;" disabled readonly data-lan-eng="This is a message written by the agent"></textarea>
						</div>
					</div>
				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Flight Information"> </h2>
			<div class="card-panel jw-mgt16">
				<h3 class="grid-wrap-title" data-lan-eng="Departure flight"></h3>

				<div class="grid-wrap jw-mgt12">
					<div class="grid-item">
						<label class="label-name" for="out_flight_no" data-lan-eng="Flight number"></label>
						<input id="out_flight_no" type="text" class="form-control" value="PR467" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="out_depart_dt" data-lan-eng="Departure date and time"></label>
						<input id="out_depart_dt" type="text" class="form-control" value="2025-04-19 12:20" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="out_arrive_dt" data-lan-eng="Arrival time"></label>
						<input id="out_arrive_dt" type="text" class="form-control" value="2025-04-19 14:20" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="out_depart_airport" data-lan-eng="Departure point"></label>
						<input id="out_depart_airport" type="text" class="form-control" value="Manila (MNL)" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="out_arrive_airport" data-lan-eng="Destination"></label>
						<input id="out_arrive_airport" type="text" class="form-control" value="Incheon (ICN)" disabled>
					</div>

					<div class="grid-item"></div>
				</div>
			</div>
			
			<div class="card-panel jw-mgt16">
				<h3 class="grid-wrap-title" data-lan-eng="Return trip"></h3>

				<div class="grid-wrap jw-mgt12">
					<div class="grid-item">
						<label class="label-name" for="in_flight_no" data-lan-eng="Flight number"></label>
						<input id="in_flight_no" type="text" class="form-control" value="PR468" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="in_depart_dt" data-lan-eng="Departure date and time"></label>
						<input id="in_depart_dt" type="text" class="form-control" value="2025-04-24 15:05" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="in_arrive_dt" data-lan-eng="Arrival time"></label>
						<input id="in_arrive_dt" type="text" class="form-control" value="2025-04-24 17:05" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="in_depart_airport" data-lan-eng="Departure point"></label>
						<input id="in_depart_airport" type="text" class="form-control" value="Incheon (ICN)" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="in_arrive_airport" data-lan-eng="Destination"></label>
						<input id="in_arrive_airport" type="text" class="form-control" value="Manila (MNL)" disabled>
					</div>

					<div class="grid-item"></div>
				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Customer Information">  </h2>
			<div class="card-panel jw-mgt16">
				<div class="grid-wrap">

					<div class="grid-item">
						<label class="label-name" for="cust_name" data-lan-eng="Name"></label>
						<input id="cust_name" type="text" class="form-control" value="Jose Ramirez" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="cust_email" data-lan-eng="Email"></label>
						<input id="cust_email" type="email" class="form-control" value="ramirez@gmail.com" disabled>
					</div>

					<div class="grid-item">
						<label class="label-name" for="cust_phone" data-lan-eng="Contacts"></label>
						<input id="cust_phone" type="text" class="form-control" value="917 123 4567" disabled>
					</div>

				</div>
			</div>


			<h2 class="section-title jw-mgt32" data-lan-eng="Travel Customer Information">  </h2>
			<div class="card-panel jw-mgt16">
				<!--    -->
				<div class="tableA-scroll">
					<table class="jw-tableA booking-detail">
						<colgroup>
							<col style="width:50px;"><!-- No -->
							<col style="width:100px;"><!--   -->
							<col style="width:160px;"><!--   -->
							<col style="width:160px;"><!--    -->
							<col style="width:120px;"><!--  -->
							<col style="width:160px;"><!--  -->
							<col style="width:160px;"><!--  -->
							<col style="width:120px;"><!--  -->
							<col style="width:100px;"><!--  -->
							<col style="width:160px;"><!--  -->
							<col style="width:160px;"><!--  -->
							<col style="width:180px;"><!--  -->
							<col style="width:160px;"><!--   -->
							<col style="width:160px;"><!--   -->
							<col style="width:240px;"><!--   -->
						</colgroup>

						<thead>
							<tr>
								<th>No</th>
								<th data-lan-eng="Main traveler"> </th>
								<th data-lan-eng="Number of people option"> </th>
								<th data-lan-eng="Visa application status">  </th>
								<th data-lan-eng="Title"></th>
								<th data-lan-eng="Name"></th>
								<th data-lan-eng="Nature"></th>
								<th data-lan-eng="Gender"></th>
								<th data-lan-eng="Age"></th>
								<th data-lan-eng="Date of birth"></th>
								<th data-lan-eng="Country of origin"></th>
								<th data-lan-eng="Passport number"></th>
								<th data-lan-eng="Passport Issue Date"> </th>
								<th data-lan-eng="Passport expiration date"> </th>
								<th data-lan-eng="Passport photo"> </th>
							</tr>
						</thead>

						<tbody>
							<!-- row 1 -->
							<tr>
								<td class="is-center">1</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" checked>
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 2 -->
							<tr>
								<td class="is-center">2</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1">
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 3 -->
							<tr>
								<td class="is-center">3</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 4 -->
							<tr>
								<td class="is-center">4</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 5 -->
							<tr>
								<td class="is-center">5</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 6 -->
							<tr>
								<td class="is-center">6</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 7 -->
							<tr>
								<td class="is-center">7</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 8 -->
							<tr>
								<td class="is-center">8</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 9 -->
							<tr>
								<td class="is-center">9</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>

							<!-- row 10 -->
							<tr>
								<td class="is-center">10</td>
								<td class="is-center">
									<label class="jw-radio jw-self-center">
										<input type="radio" name="radio1" >
										<i class="icon"></i>
									</label>
								</td>

								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Adult</option>
											<option>Child</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Not applied</option>
											<option></option>
										</select></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>MR</option>
											<option>MRS</option>
											<option>MS</option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Ramirez"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Jose"></div>
								</td>
								<td class="show">
									<div class="cell">
										<select class="select" disabled>
											<option selected>Male</option>
											<option></option>
										</select></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" value="40" disabled></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="19710101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="Philippines"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="P1234567"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20200101"></div>
								</td>
								<td class="is-center">
									<div class="cell"><input type="text" class="form-control" disabled value="20300101"></div>
								</td>
								<td class="is-center">
									<div class="cell">
										<div class="field-row jw-center">
											<div class="jw-center jw-gap10"><img src="../image/file.svg" alt="" data-lan-eng="Passport photo">Passport photo</div>
											<div class="jw-center jw-gap10">
												<i></i>
												<button type="button" class="jw-button typeF" aria-label="download"><img
														src="../image/buttun-download.svg" alt=""></button>
												
											</div>
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>

				</div>

				<div class="jw-pagebox" role="navigation" aria-label="">
					<div class="contents">
						<button type="button" class="first" aria-label=" " aria-disabled="false">
							<img src="../image/first.svg" alt="">
						</button>
						<button type="button" class="prev" aria-label=" " aria-disabled="false">
							<img src="../image/prev.svg" alt="">
						</button>

						<div class="page" role="list">
							<button type="button" class="p" role="listitem">1</button>
							<button type="button" class="p" role="listitem">2</button>
							<button type="button" class="p show" role="listitem" aria-current="page">3</button>
							<button type="button" class="p" role="listitem">4</button>
							<button type="button" class="p" role="listitem">5</button>
						</div>

						<button type="button" class="next" aria-label=" " aria-disabled="false">
							<img src="../image/next.svg" alt="">
						</button>
						<button type="button" class="last" aria-label=" " aria-disabled="false">
							<img src="../image/last.svg" alt="">
						</button>
					</div>
				</div>



			</div>



		</section>

	</main>

	<!--   -->
	<script src="../js/default.js"></script>
	<script src="../js/agent.js"></script>
	<script src="../js/agent-reservation-detail.js"></script>
	<script>
		init({
			headerUrl: '../inc/header.html',
			navUrl: '../inc/nav_agent.html'
		});
	</script>
	<script>
		(function () {
			try {
				const p = new URLSearchParams(window.location.search);
				const bookingId = p.get('bookingId') || p.get('id') || '';
				if (!bookingId) return;
				const loc = document.getElementById('meetingLocationLink');
				const noti = document.getElementById('announcementsLink');
				if (loc) loc.href = `../common/reservation-location.html?bookingId=${encodeURIComponent(bookingId)}`;
				if (noti) noti.href = `../common/reservation-notices.html?bookingId=${encodeURIComponent(bookingId)}`;
			} catch (_) {}
		})();
	</script>
</body>

</html>
