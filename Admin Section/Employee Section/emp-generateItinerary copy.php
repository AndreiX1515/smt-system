<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Itinerary</title>
    <?php include '../Employee Section/includes/emp-head.php' ?>
    <link rel="stylesheet" href="../Employee Section/assets/css/emp-sidebar-navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Employee Section/assets/css/emp-generateItinerary.css?v=<?php echo time(); ?>">
</head>

<body>

    <?php include '../Employee Section/includes/emp-sidebar.php' ?>

    <!-- Main Container -->
    <div class="main-container">
        <?php include '../Employee Section/includes/emp-navbar.php' ?>

        <div class="main-content">
            <div class="form-container">
                
                <div class="card">
                    <div class="card-header">   
                        <h5>Itinerary Details</h5>
                    </div>

                    <div class="card-body">

                        <!-- Package Row -->
                        <div class="row">
                            <!-- Flight Date Dropdown -->
                            <div class="columns col-md-4">
                                <div class="column-header">
                                    <label for="flightDate">Package
                                        <span class="text-danger"> *</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <select class="form-select" id="packageSelect" name="packageSelect" required>
                                        <option selected disabled>Select Package Type</option>
                                        <?php
                                        // Execute the SQL query
                                        $sql1 = "SELECT packageName FROM package ORDER BY packageId ASC";
                                        $res1 = $conn->query($sql1);

                                        // Check if there are results
                                        if ($res1->num_rows > 0) {
                                            // Loop through the results and generate options
                                            while ($row = $res1->fetch_assoc()) {
                                                echo "<option value='" . $row['packageName'] . "'>" . $row['packageName'] . "</option>";
                                            }
                                        } else {
                                            echo "<option value=''>No companies available</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Periods, Guide Row -->
                        <div class="row">
                            <!-- Flight Date Dropdown -->
                            <div class="columns col-md-4">

                                <div class="column-header">
                                    <label for="flightDate">Periods
                                        <span class="text-danger"> *</span>
                                    </label>
                                </div>

                                <div class="datepicker-wrapper">
                                    <div class="form-group">
                                        <div class="date-range-inputs-wrapper">
                                            <div class="input-with-icon">
                                                <input type="text" class="datepicker" id="PeriodStartDate" placeholder="Start" readonly>
                                                <i class="fas fa-calendar-alt calendar-icon"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dash Separator -->
                                    <div class="dash-separator">-></div>

                                    <div class="form-group">
                                        <div class="date-range-inputs-wrapper">
                                            <div class="input-with-icon">
                                                <input type="text" class="datepicker" id="PeriodEndDate" placeholder="End" readonly>
                                                <i class="fas fa-calendar-alt calendar-icon"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>



                            <div class="columns col-md-4">
                                <div class="column-header">
                                    <label for="flightDate">Guide
                                        <span class="text-danger"> *</span>
                                    </label>
                                </div>

                                <div class="form-group">
                                    <select class="form-select" id="guideName" name="flightDate" required>
                                        <option selected disabled>Select Guide</option>
                                        <option value="John Doe">John Doe</option>
                                    </select>
                                </div>
                            </div>

                            <div class="columns col-md-4">
                                <div class="column-header">
                                    <label for="flightDate">Contact Number
                                        <span class="text-danger"> *</span>
                                    </label>
                                </div>

                                <div class="form-group d-flex flex-row align-items-center">
                                    <select class="form-select" id="countryCode" style="width: 80px;">
                                        <option value="+63" selected>+63</option>
                                        <option value="+82">+82</option>
                                    </select>
                                    <input type="text" class="form-control ms-2" id="contactNumber" name="contactNumber" min="1" placeholder="9***********" required>
                                </div>

                            </div>

                        </div>

                        <!-- Tour Areas, Hotels -->
                        <div class="row">
                            <div class="columns col-md-8">
                                <div class="column-header">
                                    <label for="flightDate">Tour Areas, Hotels <span class="text-danger"> *</span></label>
                                </div>
                                <div class="cityhotel-wrapper">
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select city-select" id="city1" name="city(1)" required>
                                                <option selected disabled>Select City</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="dash-separator">-></div>
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select hotel-select" id="hotel1" name="hotel(1)" required>
                                                <option selected disabled>Select Hotel</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="columns col-md-8">
                                <div class="cityhotel-wrapper">
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select city-select" id="city2" name="city(2)" required>
                                                <option selected disabled>Select City</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="dash-separator">-></div>
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select hotel-select" id="hotel2" name="hotel(2)" required>
                                                <option selected disabled>Select Hotel</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="columns col-md-8">
                                <div class="cityhotel-wrapper">
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select city-select" id="city3" name="city(3)" required>
                                                <option selected disabled>Select City</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="dash-separator">-></div>
                                    <div class="cityhotel-item">
                                        <div class="form-group d-flex flex-row align-items-center">
                                            <select class="form-select hotel-select" id="hotel3" name="hotel(3)" required>
                                                <option selected disabled>Select Hotel</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hotel -->
                        <!-- <div class="row">
                            <div class="column-header mb-2">
                                <label for="flightDate">Hotel
                                    <span class="text-danger"> *</span>
                                </label>
                            </div>

                            
                            <div class="columns col-md-4">
                                <div class="form-group">
                                    <select class="form-select hotel-select" id="hotel1" name="hotel1" required>
                                        <option selected disabled>Select Hotel (1)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="columns col-md-4">
                                <div class="form-group">
                                    <select class="form-select hotel-select" id="hotel2" name="hotel2" required>
                                        <option selected disabled>Select Hotel (2)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="columns col-md-4">
                                <div class="form-group">
                                    <select class="form-select hotel-select" id="hotel3" name="hotel3" required>
                                        <option selected disabled>Select Hotel (3)</option>
                                    </select>
                                </div>
                            </div>

                        </div> -->

                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>No. of Days</h5>
                    </div>

                    <div class="card-body">

                        <!-- Package Row -->
                        <div class="row">
                            <div class="columns col-md-3">
                                <div class="form-group days-select-wrapper">
                                    <label for="flightDate">No. of days<span class="text-danger"> *</span></label>
                                    <select class="form-select" id="select-days" name="numberOfDays" required>
                                        <option selected disabled>Select Number of Days</option>
                                    </select>
                                    <small class="form-text text-muted">Changing this will clear all your data on the fields.</small>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="itinerary-container" id="itinerary-container">

                </div>
            </div>

            <div class="form-footer">
                <button type="button" class="btn btn-primary" id="submitTour">Generate Itinerary</button>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="templateNameModal" tabindex="-1" aria-labelledby="templateNameModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateNameModalLabel">Enter Template Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please enter a template name before proceeding:</p>

                    <!-- Template Name Input -->
                    <div class="mt-3">
                        <label for="templateName" class="form-label">Template Name:</label>
                        <input type="text" class="form-control" id="templateName" placeholder="Enter template name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="proceedWithSubmission()">Proceed</button>
                </div>
            </div>
        </div>
    </div>



    <?php include '../Employee Section/includes/emp-scripts.php' ?>

    <!-- Datepicker Script -->
    <script>
        $(document).ready(function() {
            // Apply datepicker for PeriodStartDate
            $("#PeriodStartDate").datepicker({
                dateFormat: "yy-mm-dd",
                showAnim: "fadeIn",
                changeMonth: true,
                changeYear: true,
                yearRange: "1900:2100",
                onSelect: function(dateText) {
                    console.log("PeriodStartDate Selected: " + dateText);
                }
            });

            // Apply datepicker for PeriodEndDate
            $("#PeriodEndDate").datepicker({
                dateFormat: "yy-mm-dd",
                showAnim: "fadeIn",
                changeMonth: true,
                changeYear: true,
                yearRange: "1900:2100",
                onSelect: function(dateText) {
                    console.log("PeriodEndDate Selected: " + dateText);
                }
            });
        });
    </script>

    <!-- Data Fetch to Fields -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Cities and Hotels Data
            const cities = ["Seoul", "Busan", "Jeonju", "Jeju"];
            const hotels = {
                "Seoul": ["Lotte Hotel Seoul", "Signiel Seoul", "The Shilla Seoul", "Grand Hyatt Seoul", "InterContinental Seoul COEX"],
                "Busan": ["Park Hyatt Busan", "Paradise Hotel Busan"],
                "Jeonju": ["Lahan Hotel Jeonju"],
                "Jeju": ["Maison Glad Jeju", "Ramada Plaza Jeju"]
            };

            // Load stored hotel selections
            loadStoredHotels();

            // Populate city dropdowns
            document.querySelectorAll(".city-select").forEach((select, index) => {
                populateDropdown(select, cities, "Select City");
                select.addEventListener("change", () => updateHotelDropdown(select, index + 1));
            });

            // Function to populate dropdowns (City or Hotel)
            function populateDropdown(select, optionsList, placeholderText) {
                if (!select) return;

                select.innerHTML = `<option selected disabled>${placeholderText}</option>`;
                optionsList.forEach(optionValue => {
                    const option = document.createElement("option");
                    option.value = optionValue;
                    option.textContent = optionValue;
                    select.appendChild(option);
                });
            }

            // Function to update the corresponding Hotel dropdown when a City is selected
            function updateHotelDropdown(citySelect, rowIndex) {
                const row = citySelect.closest(".row"); // Get the parent row
                const hotelSelect = row.querySelector(".hotel-select"); // Find corresponding hotel select

                if (!hotelSelect) return;

                const selectedCity = citySelect.value;
                hotelSelect.innerHTML = `<option selected disabled>Select Hotel</option>`; // Reset hotels

                if (hotels[selectedCity]) {
                    populateDropdown(hotelSelect, hotels[selectedCity], "Select Hotel");
                }

                console.log(`Row ${rowIndex}: City Selected - ${selectedCity}`);
            }

            // Function to load stored hotels from localStorage
            function loadStoredHotels() {
                const storedHotels = JSON.parse(localStorage.getItem("selectedHotels")) || {};
                document.querySelectorAll(".hotel-select").forEach((select, index) => {
                    const row = select.closest(".row");
                    const citySelect = row.querySelector(".city-select");
                    const rowIndex = index + 1;

                    if (citySelect) {
                        const selectedCity = citySelect.value;
                        if (selectedCity && hotels[selectedCity]) {
                            populateDropdown(select, hotels[selectedCity], "Select Hotel");
                            if (storedHotels[selectedCity]) {
                                select.value = storedHotels[selectedCity];
                            }
                        }
                    }

                    console.log(`Row ${rowIndex}: Loaded City - ${citySelect?.value || "None"}, Hotel - ${select.value || "None"}`);
                });
            }

            // Save selected hotels to localStorage
            document.body.addEventListener("change", (event) => {
                if (event.target.classList.contains("hotel-select")) {
                    const row = event.target.closest(".row");
                    const citySelect = row.querySelector(".city-select");
                    const rowIndex = Array.from(document.querySelectorAll(".row")).indexOf(row) + 1;
                    if (!citySelect) return;

                    const selectedCity = citySelect.value;
                    const selectedHotel = event.target.value;

                    let storedHotels = JSON.parse(localStorage.getItem("selectedHotels")) || {};
                    storedHotels[selectedCity] = selectedHotel;
                    localStorage.setItem("selectedHotels", JSON.stringify(storedHotels));

                    console.log(`Row ${rowIndex}: City - ${selectedCity}, Hotel - ${selectedHotel}`);
                }
            });

            // Observe dynamically added elements
            const observer = new MutationObserver(() => {
                document.querySelectorAll(".hotel-select").forEach(select => {
                    if (!select.hasAttribute("data-initialized")) {
                        select.setAttribute("data-initialized", "true");
                        const row = select.closest(".row");
                        const citySelect = row.querySelector(".city-select");
                        const rowIndex = Array.from(document.querySelectorAll(".row")).indexOf(row) + 1;
                        if (citySelect) {
                            updateHotelDropdown(citySelect, rowIndex);
                        }
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const selectDays = document.getElementById("select-days");
            const itineraryContainer = document.getElementById("itinerary-container");
            const formFooter = document.querySelector(".form-footer"); // Select the form-footer
            const submitBtn = document.getElementById("submit-itinerary");

            // Korean Tour Data
            const koreanTourAreas = ["Seoul", "Busan", "Jeju", "Incheon", "Gyeongju"];
            const koreanMealPlans = ["Traditional Korean Cuisine", "Street Food Tour", "Seafood Specialty", "Vegetarian Option", "Luxury Fine Dining"];

            const hotels = [
                "Lotte Hotel Seoul",
                "Signiel Seoul",
                "The Shilla Seoul",
                "Grand Hyatt Seoul",
                "InterContinental Seoul COEX",
                "Park Hyatt Busan",
                "Paradise Hotel Busan",
                "Lahan Hotel Jeonju",
                "Maison Glad Jeju",
                "Ramada Plaza Jeju"
            ];

            // Itineraries
            const itineraries = [
                "Gyeongbokgung Palace Tour",
                "Myeongdong Shopping District",
                "Namsan Seoul Tower",
                "Bukchon Hanok Village",
                "Dongdaemun Design Plaza",
                "Busan Gamcheon Culture Village",
                "Jeju Island Lava Tubes"
            ];

            for (let num = 1; num <= 5; num++) {
                let option = document.createElement("option");
                option.value = num;
                option.textContent = `Day ${num}`;
                selectDays.appendChild(option);
            }

            // Generate itinerary cards based on selected days
            selectDays.addEventListener("change", function() {
                const selectedDays = parseInt(selectDays.value);
                itineraryContainer.innerHTML = ""; // Clear previous content

                for (let day = 1; day <= selectedDays; day++) {
                    const card = document.createElement("div");
                    card.className = "card itinerary-card mb-3"; // Bootstrap margin-bottom for spacing

                    card.innerHTML = `
                        <div class="card-header bg-primary text-white fw-bold">Day ${day}</div>
                        <div class="card-body">
                            <div class="container-fluid">
                                <div class="row mb-3">
                                    ${day === 1 
                                        ? `<div class="col-4">
                                            <label class="form-label fw-semibold">Area:</label>    
                                            <select class="form-select area-select" data-day="${day}">
                                                <option selected disabled>Select Area</option>
                                                ${koreanTourAreas.map(area => `<option value="${area}">${area}</option>`).join("")}
                                            </select>
                                        </div>`
                                        : 
                                        ["Area 1", "Area 2", "Area 3"].map(areaLabel => `
                                            <div class="col-4">
                                                <label class="form-label fw-semibold">${areaLabel}:</label>    
                                                <select class="form-select area-select" data-day="${day}">
                                                    <option selected disabled>Select ${areaLabel}</option>
                                                    ${koreanTourAreas.map(area => `<option value="${area}">${area}</option>`).join("")}
                                                </select>
                                            </div>
                                        `).join("")
                                    }
                                </div>

                                <div class="row mb-3">
                                    ${day === 1 
                                        ? `<div class="col-4">
                                            <label class="form-label fw-semibold">Meal Plan:</label>
                                            <select class="form-select meal-plan-select" data-day="${day}">
                                                <option selected disabled>Select Meal Plan</option>
                                                ${koreanMealPlans.map(meal => `<option value="${meal}">${meal}</option>`).join("")}
                                            </select>
                                        </div>`
                                        : 
                                        ["Meal Plan 1", "Meal Plan 2", "Meal Plan 3"].map(mealLabel => `
                                            <div class="col-4">
                                                <label class="form-label fw-semibold">${mealLabel}:</label>
                                                <select class="form-select meal-plan-select" data-day="${day}">
                                                    <option selected disabled>Select ${mealLabel}</option>
                                                    ${koreanMealPlans.map(meal => `<option value="${meal}">${meal}</option>`).join("")}
                                                </select>
                                            </div>
                                        `).join("")
                                    }
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Hotels:</label>
                                        <div class="row">
                                            ${["Hotel 1", "Hotel 2", "Hotel 3"].map(hotelLabel => `
                                                <div class="col-md-4 col-sm-12 mb-2">
                                                    <select class="form-select hotel-select" data-day="${day}">
                                                        <option selected disabled>Select ${hotelLabel}</option>
                                                        ${hotels.map(hotel => `<option value="${hotel}">${hotel}</option>`).join("")}
                                                    </select>
                                                </div>
                                            `).join("")}
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Itinerary:</label>
                                    </div>
                                    ${[1, 2, 3, 4, 5, 6, 7].map(num => `
                                        <div class="col-12 mb-2">
                                            <select class="form-select itinerary-select" data-day="${day}">
                                                <option selected disabled>Select Itinerary ${num}</option>
                                                ${itineraries.map(itinerary => `<option value="${itinerary}">${itinerary}</option>`).join("")}
                                            </select>
                                        </div>
                                    `).join("")}
                                </div>
                            </div>
                        </div>
                    `;

                    itineraryContainer.appendChild(card);
                }

                // Hide the form-footer when itinerary is cleared
                formFooter.style.display = selectedDays ? "flex" : "none";
            });

            document.addEventListener("change", function(event) {
                if (event.target.matches(".area-select, .hotel-select, .meal-plan-select, .itinerary-select")) {
                    const day = event.target.dataset.day;
                    if (!day) {
                        console.warn("data-day attribute is missing!");
                        return;
                    }

                    // Get ALL selected values for the specific day
                    const selectedAreas = [...document.querySelectorAll(`.area-select[data-day="${day}"]`)]
                        .map(a => a.value || "None");
                    const selectedMealPlans = [...document.querySelectorAll(`.meal-plan-select[data-day="${day}"]`)]
                        .map(m => m.value || "None");
                    const selectedHotels = [...document.querySelectorAll(`.hotel-select[data-day="${day}"]`)]
                        .map(h => h.value || "None");
                    const selectedItineraries = [...document.querySelectorAll(`.itinerary-select[data-day="${day}"]`)]
                        .map(i => i.value || "None");

                    console.log(JSON.stringify({
                        Day: day,
                        Areas: selectedAreas,
                        MealPlans: selectedMealPlans,
                        Hotels: selectedHotels,
                        Itineraries: selectedItineraries
                    }, null, 2));
                }
            });
        });
    </script>

    <script>
        document.getElementById("submitTour").addEventListener("click", function() {
            $("#templateNameModal").modal("show");
        });

        // Function to proceed after entering the template name
        function proceedWithSubmission() {
            const templateName = document.getElementById("templateName")?.value.trim();

            if (!templateName) {
                alert("Please enter a template name before proceeding.");
                return;
            }

            const itineraryData = [];

            const selectedPackage = document.getElementById("packageSelect")?.value.trim() || "None";
            const noOfDays = document.getElementById("select-days")?.value.trim() || "None";
            const startDate = document.getElementById("PeriodStartDate")?.value.trim() || "None";
            const endDate = document.getElementById("PeriodEndDate")?.value.trim() || "None";
            const guideName = document.getElementById("guideName")?.value.trim() || "None";
            const countryCode = document.getElementById("countryCode")?.value.trim() || "None";
            const contactNumber = document.getElementById("contactNumber")?.value.trim() || "None";

            const city1 = document.getElementById("city1")?.value.trim() || "None";
            const hotel1 = document.getElementById("hotel1")?.value.trim() || "None";
            const city2 = document.getElementById("city2")?.value.trim() || "None";
            const hotel2 = document.getElementById("hotel2")?.value.trim() || "None";
            const city3 = document.getElementById("city3")?.value.trim() || "None";
            const hotel3 = document.getElementById("hotel3")?.value.trim() || "None";

            document.querySelectorAll(".itinerary-card").forEach(dayCard => {
                const day = dayCard.querySelector(".hotel-select")?.dataset.day || "Unknown";

                const selectedAreas = [...dayCard.querySelectorAll(".area-select[data-day]")].map(area => area.value.trim()).filter(value => value !== "");
                const selectedMealPlans = [...dayCard.querySelectorAll(".meal-plan-select[data-day]")].map(meal => meal.value.trim()).filter(value => value !== "");
                const selectedHotels = [...dayCard.querySelectorAll(".hotel-select")].map(select => select.value.trim()).filter(value => value !== "");
                const selectedItineraries = [...dayCard.querySelectorAll(".itinerary-select")].map(select => select.value.trim()).filter(value => value !== "");

                itineraryData.push({
                    day,
                    areas: selectedAreas.length ? selectedAreas : ["None"],
                    meal_plans: selectedMealPlans.length ? selectedMealPlans : ["None"],
                    hotels: selectedHotels.length ? selectedHotels : ["None"],
                    itineraries: selectedItineraries.length ? selectedItineraries : ["None"]
                });
            });

            console.group("📌 Submitting Itinerary Data");
            console.table({
                selectedPackage,
                startDate,
                endDate,
                guideName,
                city1,
                hotel1,
                city2,
                hotel2,
                city3,
                hotel3
            });
            console.table(itineraryData);
            console.groupEnd();

            const submitButton = document.getElementById("submitTour");
            submitButton.disabled = true;

            $.ajax({
                url: "../Employee Section/functions/emp-saveItinerary.php",
                type: "POST",
                data: {
                    noOfDays: noOfDays,
                    package: selectedPackage,
                    period_start: startDate,
                    period_end: endDate,
                    countryCode: countryCode,
                    contactNumber: contactNumber,
                    guide: guideName,
                    city1: city1,
                    hotel1: hotel1,
                    city2: city2,
                    hotel2: hotel2,
                    city3: city3,
                    hotel3: hotel3,
                    itinerary: JSON.stringify(itineraryData),
                    templateName: templateName // Pass only the template name
                },
                dataType: "json",
                success: function(response) {
                    submitButton.disabled = false;
                    if (response.status === "success") {
                        alert("Itinerary successfully created!");
                        location.reload();
                    } else {
                        console.error("❌ Server Error:", response.message);
                        alert("❌ Error saving itinerary: " + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    submitButton.disabled = false;
                    console.error("⚠️ AJAX Error:", error);
                    console.error("⚠️ Response Text:", xhr.responseText);
                    alert("An error occurred while saving the itinerary.");
                }
            });

            $("#templateNameModal").modal("hide");
        }
    </script>

   

</body>

</html>