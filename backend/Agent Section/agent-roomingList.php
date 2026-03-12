<?php
session_start();
require "../conn.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Booking</title>

  <?php include "../Agent Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Agent Section/assets/css/agent-roomingList.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>

    <?php include "../Agent Section/includes/sidebar.php"; ?>

    <div class="main-container">

      <div class="navbar">
        <div class="page-header-wrapper">

          <!-- <div class="page-header-top">
            <div class="back-btn-wrapper">
              <button class="back-btn" id="redirect-btn">
                <i class="fas fa-chevron-left"></i>
              </button>
            </div>
          </div> -->

          <div class="page-header-content">
            <div class="page-header-text">
              <h5 class="header-title">Rooming List</h5>
            </div>
          </div>

        </div>
      </div>

      <div class="main-content">
        <!-- <div class="main-content-header">
          <h5 class="text-center">Guest Room Assignment</h5>
        </div> -->

        <div class="field-select-wrapper">
          <div class="row">
            <div class="col-md-6 columns ">
              <!-- Header Div for Label -->
              <div class="header-wrapper">
                <label for="flightDate">Select Flight Date</label>
              </div>

              <div class="select-wrapper">
                <select id="flightDate" class="form-control" required>
                  <option disabled selected>Select a Flight Date</option>
                  <?php
                  $sql1 = "SELECT DISTINCT flightDepartureDate FROM flight ORDER BY flightDepartureDate ASC";
                  $result = $conn->query($sql1);

                  if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                      $formattedFlightDate = date("F j, Y", strtotime($row['flightDepartureDate']));
                      echo "<option value='" . $row['flightDepartureDate'] . "'>" . $formattedFlightDate . "</option>";
                    }
                  } else {
                    echo "<option value='' disabled>No flights available</option>";
                  }
                  ?>
                </select>
              </div>

            </div>
          </div>

          <div class="row">

            <div class="columns col-md-6">
              <div class="header-wrapper">
                <label for="guestName">Select Guests:</label>
              </div>

              <div class="select-wrapper">
                <select id="guestName" class="form-control" multiple></select>
                <?php
                $luggageOptions = [];
                $guestLuggage = []; // Stores guestId → selected luggage mapping

                // Fetch luggage options
                $query = "SELECT concernDetailsId, details FROM concerndetails";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $luggageOptions[] = $row;
                  }
                }

                // Fetch stored luggage selections for each guest
                $query = "SELECT g.guestId, cd.concernDetailsId 
                          FROM guestluggage g
                          JOIN concerndetails cd ON g.luggageType = cd.concernDetailsId"; // Adjust your table name
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $guestLuggage[$row['guestId']] = $row['concernDetailsId'];
                  }
                }

                // Pass data to JavaScript
                echo "<script>
                        let luggageOptions = " . json_encode($luggageOptions) . ";
                        let guestLuggage = " . json_encode($guestLuggage) . ";
                      </script>";
                ?>
              </div>

            </div>

            <div class="columns col-md-6 room-type-wrapper">
              <div class="room-type-select">
                <div class="header-wrapper">
                  <label for="roomType">Room Type:</label>
                </div>

                <div class="select-wrapper">
                  <select id="roomType" class="form-control">
                    <option value="Single">Single Supplement (Max 1)</option>
                    <option value="Twin">Twin Room (Max 2)</option>
                    <option value="Double">Double Room (Max 2)</option>
                    <option value="Triple">Triple Room (Min 2 - Max 3)</option>
                  </select>
                </div>
              </div>

              <div class="assign-btn-wrapper">
                <button class="btn btn-primary" onclick="assignRoom()">Assign</button>
              </div>

            </div>
          </div>
        </div>

        <div class="table-wrapper">
          <div class="header-wrapper">
            <h5>Assigned Rooms</h5>
          </div>
          
          <div class="table-container">
            <table class="assigned-rooms-table">
                <thead class="thead-dark">
                  <tr>
                    <th>#</th>
                    <th>AGE</th>
                    <th>GIVEN NAME</th>
                    <th>SURNAME</th>
                    <th>FULLNAME</th>
                    <th>DOB</th>
                    <th>NATIONALITY</th>
                    <th>PASSPORT</th>
                    <th>EXPIRATION DATE</th>
                    <th>SEX</th>
                    <th>ROOMING</th>
                    <th>LUGGAGE (AIR TICKET)</th>
                    <th></th>
                  </tr>
                </thead>

                <tbody id="assignedRoomsTable"></tbody>
              </table>

          </div>
          
          <div class="table-footer">
            <button class="btn btn-success btn-sm" onclick="generateExcel()">Download</button>
            <button class="btn btn-success btn-sm" id="saveAssignments">Save</button>
          </div>

          

        </div>

      </div>
    </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <!-- Combined script working -->
  <script>
    let guests = [];  // Stores all guests fetched from PHP
    let availableGuests = [];  // Stores guests available for assignment
    let rooms = [];
    let removedLuggage = [];

    const roomCapacities = {
      Single: { min: 1, max: 1 },
      Twin:   { min: 2, max: 2 },
      Double: { min: 2, max: 2 },
      Triple: { min: 3, max: 3 }, // Example: now Triple can have min 2 guests
    };

    document.getElementById('saveAssignments').addEventListener('click', function () 
    {
      let roomAssignments = [];
      let luggageAssignments = [];

      $.ajax(
      {
        url: '../Agent Section/functions/fetchMaxRoomNumber.php', // Create this PHP file
        type: 'GET',
        dataType: 'json',
        success: function(response) 
        {
          let maxRoomNumber = response.maxRoomNumber || 0; // Start from 0 if no existing data

          let roomAssignments = [];
          let luggageAssignments = [];

          // Assign room numbers dynamically
          rooms.forEach((room) => 
          {
            maxRoomNumber++; // Increment for the next room
            
            room.guests.forEach(guest => 
            {
              roomAssignments.push(
              {
                transactNo: guest.transactNo,
                guestId: guest.id,
                roomType: room.type,
                roomNumber: maxRoomNumber // Assign based on fetched maxRoomNumber
              });

              // Collect luggage data for each guest
              let luggageItems = Array.from(document.querySelectorAll(`#luggageContainer-${guest.id} select`)).map(select => select.value);
              luggageItems.forEach(luggage => {
                luggageAssignments.push(
                {
                  transactNo: guest.transactNo,
                  guestId: guest.id,
                  luggageId: luggage
                });
              });
            });
          });

          // Ensure there's data to send
          if (roomAssignments.length === 0) 
          {
            alert("No room assignments to save.");
            return;
          }

          // Send AJAX request to insert room assignments
          $.ajax(
          {
            url: '../Agent Section/functions/agent-addRoomingList.php',
            type: 'POST',
            data: { 
              roomAssignments: JSON.stringify(roomAssignments),
              luggageAssignments: JSON.stringify(luggageAssignments) // Include luggage data in the same request
            },
            success: function(response) {
              console.log(response);
              alert("Room assignments and luggage details saved successfully!");
            },
            error: function(xhr, status, error) {
              console.error('Error saving data:', error);
              alert("Failed to save room assignments and luggage details.");
            }
          });
        },
        error: function(xhr, status, error) 
        {
          console.error('Error fetching max room number:', error);
          alert("Failed to fetch max room number.");
        }
      });
    });

    // Fetch guests when flight date changes
    $('#flightDate').on('change', function () 
    {
      let flightDate = $(this).val();
      let agentCode = "<?php echo $agentCode; ?>";
      console.log("Luggage Options:", luggageOptions);
      $('#guestName').html('<option selected disabled>Loading guests...</option>');

      if (flightDate) 
      {
        $.ajax(
        {
          url: '../Agent Section/functions/fetchGuest.php', 
          type: 'POST',
          data: { flightDate: flightDate, agentCode: agentCode },
          success: function (response) 
          {
            console.log(response);
            let guestSelect = $('#guestName');
            guestSelect.empty(); // Clear existing options

            let data = JSON.parse(response);
            
            // Populate assigned guests in room list
            if (data.assignedGuests.length > 0)
            {
              rooms = []; // Clear existing rooms before reloading
              data.assignedGuests.forEach(guest => 
              {
                let room = 
                {
                  type: guest.roomType,
                  guests: [{ ...guest }],
                  roomNumber: guest.roomNumber
                };
                rooms.push(room);
              });
              updateRoomList();
            }

            // Populate unassigned guests in select dropdown
            if (data.unassignedGuests.length > 0) 
            {
              data.unassignedGuests.forEach(guest => 
              {
                guestSelect.append(new Option(guest.name, guest.id));
              });

              guests = data.unassignedGuests; // Store unassigned guests for reference
            } 
            else 
            {
              $('#guestName').html('<option selected disabled>No guests found</option>');
            }
          },
          error: function (xhr, status, error) 
          {
            console.error('Error fetching guests:', error);
            $('#guestName').html('<option selected disabled>No guests found</option>');
          }
        });
      } 
      else 
      {
        $('#guestName').html('<option selected disabled>Select a guest</option>');
      }
    });

    function assignRoom() 
    {
      let guestSelect = document.getElementById('guestName');
      let selectedGuests = Array.from(guestSelect.selectedOptions).map(opt => 
      {
        let guestData = guests.find(g => g.id == opt.value);
        return { ...guestData };
      });

      let roomType = document.getElementById('roomType').value;
      let minCapacity = getMinCapacity(roomType);
      let maxCapacity = getMaxCapacity(roomType);

      if (selectedGuests.length < minCapacity || selectedGuests.length > maxCapacity) 
      {
        alert(`A ${roomType} room must have between ${minCapacity} and ${maxCapacity} guests.`);
        return;
      }

      // Fetch latest room number before assigning
      let transactNo = selectedGuests[0]?.transactNo; // Assuming all guests share the same transactNo
      if (!transactNo) 
      {
        alert("Error: Missing transaction number.");
        return;
      }

      // Prevent duplicate assignments
      let alreadyAssigned = selectedGuests.some(guest =>
        rooms.some(room => room.guests.some(g => g.id == guest.id))
      );
      if (alreadyAssigned) 
      {
        alert("One or more guests are already assigned to a room.");
        return;
      }

      $.ajax(
      {
        url: '../Agent Section/functions/fetchMaxRoomNumber.php',
        type: 'POST',
        data: { transactNo: transactNo },
        success: function (response) 
        {
          let lastRoomNumber = !isNaN(parseInt(response)) ? parseInt(response) : 0; // Default to 0 if no rooms exist
          let nextRoomNumber = lastRoomNumber + 1; // Increment for new room assignment

          // Update available guests list after assignment
          guests = guests.filter(g => !selectedGuests.some(sg => sg.id == g.id));

          // Add luggage types to selected guests before assigning to room
          selectedGuests.forEach(guest => 
          {
            // Assuming guestLuggage is a global object holding luggage types by guestId
            guest.luggageTypes = guestLuggage[guest.id] || [];
          });

          let room = { type: roomType, guests: selectedGuests, roomNumber: nextRoomNumber };
          rooms.push(room);

          updateRoomList();
          updateGuestDropdown(); // Refresh guest dropdown after assignment
        },
        error: function (xhr, status, error) 
        {
          console.error("Error fetching last room number:", error);
          alert("Failed to fetch room number.");
        }
      });
    }

    // Function to update the room list
    function updateRoomList() 
    {
      let assignedRoomsTable = document.getElementById('assignedRoomsTable');
      assignedRoomsTable.innerHTML = ''; // Clear the existing table

      let rowNumber = 1;

      rooms.forEach((room, roomIndex) => 
      {
        let firstGuest = true;

        room.guests.forEach((guest) => 
        {
          let row = assignedRoomsTable.insertRow();
          row.setAttribute("data-guest-id", guest.id);
          row.setAttribute("data-transact-no", guest.transactNo);

          // Get the count of luggage for the guest
          let luggageCount = (guest.luggageType || []).length;
          console.log(`Guest ID: ${guest.id}, Number of Luggage: ${luggageCount}`);

          // Generate container with multiple luggage selects for each guest
          let luggageSelectGroup = `
            <div id="luggageContainer-${guest.id}" class="luggage-group" data-guest-id="${guest.id}">
              <div id="luggageSelects-${guest.id}">`;

          if (luggageCount > 0) 
          {
            for (let i = 0; i < luggageCount; i++) 
            {
              luggageSelectGroup += `
                <select class="form-control" name="luggageSelect-${guest.id}[]" 
                  style="width: 100%; display: block; margin-bottom: 5px;" 
                  id="luggageSelect-${guest.id}-${i}">
                  <option value="">Select Luggage</option>`;

              luggageOptions.forEach(option => 
              {
                const selected = option.concernDetailsId == guest.luggageType[i] ? 'selected' : '';
                luggageSelectGroup += `<option value="${option.concernDetailsId}" ${selected}>${option.details}</option>`;
              });

              luggageSelectGroup += `</select>`;
            }
          }

          luggageSelectGroup += `
            </div> <!-- end of luggageSelects container -->
              <button type="button" class="btn btn-sm btn-primary mt-1" style="margin-top: 5px;" onclick="addLuggageSelect(${guest.id})">Add</button>
              <button type="button" class="btn btn-sm btn-danger mt-1" style="margin-top: 5px; margin-left: 5px;" onclick="removeLuggageSelect(${guest.id})">Remove</button>
            </div>`;

          row.innerHTML = `
            <td style="text-align: center; vertical-align: middle;">${rowNumber++}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.age || "N/A"}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.name.split(" ")[0]}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.name.split(" ").slice(-1).join(" ")}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.name}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.dob || "N/A"}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.nationality || "N/A"}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.passport || "N/A"}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.passportExp || "N/A"}</td>
            <td style="text-align: center; vertical-align: middle;">${guest.sex || "N/A"}</td>
            ${firstGuest ? `<td style="text-align: center;" class="room-type" rowspan="${room.guests.length}">${room.type.toUpperCase()}</td>` : ''} 
            <td style="text-align: center; vertical-align: middle;">
              ${luggageSelectGroup}
            </td>
            ${firstGuest ? `<td style="vertical-align: middle;" rowspan="${room.guests.length}"> 
              <button style="display: block; margin: auto;" class="btn btn-danger btn-sm" onclick="removeRoom(${roomIndex})">
                Remove
              </button>
            </td>` : ''}`;

          firstGuest = false;
        });
      });
    }

    // Add a luggage select dropdown dynamically
    function addLuggageSelect(guestId) 
    {
      const container = document.getElementById(`luggageSelects-${guestId}`);
      const selectCount = container.querySelectorAll('select').length;

      let select = document.createElement('select');
      select.className = 'form-control';
      select.name = `luggageSelect-${guestId}[]`;
      select.id = `luggageSelect-${guestId}-${selectCount}`;
      select.style.cssText = 'width: 100%; display: block; margin-bottom: 5px;';

      let defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = 'Select Luggage';
      select.appendChild(defaultOption);

      luggageOptions.forEach(option => {
        let opt = document.createElement('option');
        opt.value = option.concernDetailsId;
        opt.textContent = option.details;
        select.appendChild(opt);
      });

      container.appendChild(select); // Append to the luggageSelects container
    }

    function generateLuggageSelects(guestId) 
    {
      let html = "";

      if (guestLuggage[guestId]) 
      {
        // Ensure luggageIds are always an array
        let luggageIds = Array.isArray(guestLuggage[guestId]) ? guestLuggage[guestId] : [guestLuggage[guestId]];

        // Iterate through all luggage types assigned to the guest
        luggageIds.forEach((luggageId, index) => 
        {
          let select = `<select class="form-control" name="luggageSelect-${guestId}[]" style="width: 100%; display: block;" id="luggageSelect-${guestId}-${index}">
                          <option value="">Select Luggage</option>`;

          // Populate luggage options dynamically
          luggageOptions.forEach(option => 
          {
            select += `<option value="${option.concernDetailsId}" ${option.concernDetailsId == luggageId ? 'selected' : ''}>${option.details}</option>`;
          });

          select += `</select>`;
          html += select;
        });
      }

      return html;
    }

    // Remove the last luggage select dropdown for a guest
    function removeLuggageSelect(guestId) 
    {
      const container = document.getElementById(`luggageSelects-${guestId}`);
      const selects = container.querySelectorAll('select');

      if (selects.length > 0) 
      {
        container.removeChild(selects[selects.length - 1]);
      }
    }

    // Remove a room and reassign the guests to the unassigned list
    function removeRoom(index) 
    {
      let removedGuests = rooms[index].guests;
      guests = [...guests, ...removedGuests];
      updateGuestDropdown();
      rooms.splice(index, 1);
      updateRoomList();
    }

    // Update the guest dropdown
    function updateGuestDropdown() 
    {
      let guestSelect = document.getElementById('guestName');
      guestSelect.innerHTML = '';
      if (guests.length > 0) 
      {
        guests.forEach(guest => 
        {
          let option = document.createElement('option');
          option.value = guest.id;
          option.textContent = guest.name;
          guestSelect.appendChild(option);
        });
      } 
      else 
      {
        guestSelect.innerHTML = '<option selected disabled>No guests available</option>';
      }
      sortGuestDropdown();
    }

    // Sort the guest dropdown alphabetically
    function sortGuestDropdown() 
    {
      let guestSelect = document.getElementById('guestName');
      let options = Array.from(guestSelect.options);
      options.sort((a, b) => a.textContent.localeCompare(b.textContent));
      guestSelect.innerHTML = '';
      options.forEach(option => guestSelect.appendChild(option));
    }

    // Get minimum capacity based on room type
    function getMinCapacity(roomType) 
    {
      switch (roomType) 
      {
        case 'Single':
          return 1;
        case 'Twin':
        case 'Double':
          return 2;
        case 'Triple':
          return 3;
        default:
          return 1;
      }
    }

    // Get maximum capacity based on room type
    function getMaxCapacity(roomType) 
    {
      switch (roomType) 
      {
        case 'Single':
          return 1;
        case 'Twin':
        case 'Double':
          return 2;
        case 'Triple':
          return 3;
        default:
          return 1;
      }
    }
  </script>

  <!-- Dynamic addition of guest in the table as well as the request script
  <script>
    let guests = $("#guestName option").map(function() {
        return { value: $(this).val(), text: $(this).text() };
    }).get();  // Fetch guest details from PHP
    let availableGuests = [...guests];  // Dynamic list for UI updates
    let rooms = [];
    let globalGuestIndex = 0; // 🔹 Unique index across all rooms

    function updateGuestList()
    {
      let guestSelect = document.getElementById('guestName');
      guestSelect.innerHTML = ''; // 🛑 Clear previous options

      availableGuests.forEach(guest => 
      {
        let option = document.createElement('option');
        option.value = guest.id;
        option.textContent = guest.name;
        guestSelect.appendChild(option);
      });
    }

    function assignRoom() 
    {
      let guestSelect = document.getElementById('guestName');
      let selectedGuests = Array.from(guestSelect.selectedOptions).map(opt => 
      {
        let guestData = guests.find(g => g.id == opt.value);  // Fetch full guest details
        return { ...guestData }; // Return full guest object
      });

      let roomType = document.getElementById('roomType').value;

      let minCapacity = getMinCapacity(roomType);
      let maxCapacity = getMaxCapacity(roomType);

      if (selectedGuests.length < minCapacity || selectedGuests.length > maxCapacity) 
      {
        alert(`A ${roomType} room must have between ${minCapacity} and ${maxCapacity} guests.`);
        return;
      }

      // 🛑 Remove assigned guests from available list
      availableGuests = availableGuests.filter(g => !selectedGuests.some(sg => sg.id == g.id));

      // ✅ Remove selected guests from dropdown
      selectedGuests.forEach(guest => 
      {
        let optionToRemove = guestSelect.querySelector(`option[value="${guest.id}"]`);
        if (optionToRemove) 
        {
            optionToRemove.remove();
        }
      });

      // Store assigned room
      let room = { type: roomType, guests: selectedGuests };
      rooms.push(room);

      updateRoomList();
    }

    function updateRoomList() 
    {
      let assignedRoomsTable = document.getElementById('assignedRoomsTable');
      assignedRoomsTable.innerHTML = '';  // Clear previous rows

      let rowNumber = 1; // Initialize guest counter
      globalGuestIndex = 0; // Reset when updating list

      rooms.forEach((room, roomIndex) => 
      {
        let firstGuest = true; // Track the first row for rowspan effect

        room.guests.forEach((guest) => 
        {
          let row = assignedRoomsTable.insertRow();

          row.innerHTML = `
            <td style="text-align: center; vertical-align: middle;">${rowNumber++}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.age || "N/A"}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.name.split(" ")[0]}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.name.split(" ").slice(-1).join(" ")}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.name}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.dob || "N/A"}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.nationality || "N/A"}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.passport || "N/A"}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.passportExp || "N/A"}</td> 
            <td style="text-align: center; vertical-align: middle;">${guest.sex || "N/A"}</td> 
            ${firstGuest ? `<td style="text-align: center;
                  vertical-align: middle;" rowspan="${room.guests.length}">${room.type.toUpperCase()}</td>` : ''} 
            <td style="text-align: center; vertical-align: middle; width: 200px; white-space: nowrap; overflow: hidden;">
              <div id="luggageContainer-${globalGuestIndex}"></div> 
              <button type="button" class="btn btn-success btn-sm" onclick="addLuggageSelect(${globalGuestIndex})">+</button>
              <button type="button" class="btn btn-danger btn-sm" onclick="removeLuggageSelect(${globalGuestIndex})">-</button>
            </td>
            ${firstGuest ? `<td style="vertical-align: middle;" rowspan="${room.guests.length}">
              <button style="display: block; margin: auto;" class="btn btn-danger btn-sm" onclick="removeRoom(${roomIndex})">
                Remove
              </button>
            </td>` : ''}`;

          firstGuest = false; // Prevent rowspan duplication in next guest rows
          globalGuestIndex++; // 🔹 Increment for each guest across rooms
        });
      });
    }

    function addLuggageSelect(guestIndex) 
    {
      let container = document.getElementById(`luggageContainer-${guestIndex}`);

      let select = document.createElement("select");
      select.style.width = "100%";
      select.style.display = "block"; // Ensures proper positioning
      select.classList.add("form-control");;

      luggageOptions.forEach(option => 
      {
        let opt = document.createElement("option");
        opt.value = option.concernDetailsId;
        opt.textContent = option.details;
        select.appendChild(opt);
      });

      // Append to container (adds at the end)
      container.appendChild(select);
    }

    // Function to remove last luggage select
    function removeLuggageSelect(guestIndex) 
    {
      let container = document.getElementById(`luggageContainer-${guestIndex}`);
      if (container.children.length > 0) 
      {
        container.removeChild(container.lastChild);
      }
    }

    function removeRoom(index) 
    {
      let guestSelect = document.getElementById('guestName');

      // Restore guests to available list
      rooms[index].guests.forEach(guest => 
      {
        if (!availableGuests.some(g => g.id == guest.id)) 
        {
          availableGuests.push(guest);

          // ✅ Add guest back to the dropdown
          let option = document.createElement('option');
          option.value = guest.id;
          option.textContent = guest.name;
          guestSelect.appendChild(option);
        }
      });

      // Sort the dropdown after adding guests back
      sortGuestDropdown();

      // Remove the room from the list
      rooms.splice(index, 1);
      updateRoomList();
    }

    function sortGuestDropdown() 
    {
      let guestSelect = document.getElementById('guestName');
      let options = Array.from(guestSelect.options);

      options.sort((a, b) => a.textContent.localeCompare(b.textContent));

      guestSelect.innerHTML = ''; // Clear existing options
      options.forEach(option => guestSelect.appendChild(option)); // Append sorted options
    }

    function getMinCapacity(roomType) 
    {
      switch (roomType) 
      {
        case 'twin':
        case 'double':
          return 1; // Can be occupied by 1 or 2 guests
        case 'triple':
          return 2; // Must have at least 2 guests
        default:
          return 1;
      }
    }

    function getMaxCapacity(roomType) 
    {
      switch (roomType) 
      {
        case 'twin':
        case 'double':
          return 2; // Max 2 guests
        case 'triple':
          return 3; // Max 3 guests
        default:
          return 1;
      }
    }

    // ✅ Initialize guest list on page load
    document.addEventListener("DOMContentLoaded", updateGuestList);
  </script>

  Dynamic Guest Info 
  <script>
    $('#flightDate').on('change', function () 
    {
      var flightDate = $(this).val();
      var agentCode = "<?php echo $agentCode; ?>";
      
      // Clear guest dropdown while loading
      $('#guestName').html('<option selected disabled>Loading guests...</option>');

      if (flightDate) 
      {
        $.ajax(
        {
          url: '../Agent Section/functions/fetchGuest.php', // PHP file to handle request
          type: 'POST',
          data: { flightDate: flightDate,
                  agentCode: agentCode},
          success: function (response) 
          {
            console.log(response);
            // Parse response and update guest dropdown
            $('#guestName').html(response);
          },
          error: function (xhr, status, error) 
          {
            console.error('Error fetching guests:', error);
            $('#guestName').html('<option selected disabled>No guests found</option>');
          }
        });
      } 
      else 
      {
        $('#guestName').html('<option selected disabled>Select a guest</option>'); // Reset if no flight date
      }
    });
  </script> -->

  <!-- Generate to excel Script -->
  <script>
    let exportMode = false; // Global flag

    function generateExcel() {
      let table = document.getElementById("assignedRoomsTable");

      if (!table || table.rows.length === 0) {
        alert("No data available to export.");
        return;
      }

      let data = [];
      let thead = table.querySelector("thead");

      if (thead) {
        let headers = Array.from(thead.rows[0].cells).map(cell => cell.innerText.trim());
        data.push(headers); // Add headers
      } else {
        data.push(["#", "AGE", "GIVEN NAME", "SURNAME", "FULLNAME", "DOB", "NAT", "PASSPORT", "D of E", "SEX", "ROOMING", "LUGGAGE (AIR TICKET)"]);
      }

      let rows = table.querySelectorAll("tbody tr");
      rows.forEach(row => {
        let rowData = [];
        let cells = row.cells;

        for (let j = 0; j < cells.length - 1; j++) { // Exclude last empty column
          let cell = cells[j];
          let selects = cell.querySelectorAll("select");

          if (selects.length > 0) {
            let selectedTexts = Array.from(selects).map(select => select.options[select.selectedIndex].text);
            rowData.push(selectedTexts.join(", "));
          } else {
            rowData.push(cell.innerText.trim());
          }
        }

        data.push(rowData);
      });

      let ws = XLSX.utils.aoa_to_sheet(data);
      let wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, "Room Assignments");
      XLSX.writeFile(wb, `room_assignments_${new Date().toISOString().slice(0, 10)}.xlsx`);
    }

    // Ensure this script runs after the DOM is ready
    $(document).ready(function() {
      $('#exportToExcel').on('click', function() {
        exportMode = true;
        updateRoomList(); // Remove buttons
        generateExcel(); // Export to Excel
        exportMode = false;
        updateRoomList(); // Restore buttons
      });
    });
  </script>

</body>

</html>