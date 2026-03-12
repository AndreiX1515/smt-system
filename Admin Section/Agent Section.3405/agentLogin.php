<?php
require "../conn.php"; // Move up to the parent directory


session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<script>console.log('Session Data:', " . json_encode($_SESSION, JSON_PRETTY_PRINT) . ");</script>";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['flightid'])) {
    $_SESSION['flightid'] = htmlspecialchars($_POST['flightid']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <meta charset="UTF-8">

    <?php include '../Agent Section/includes/head.php' ?>

    <link href="../Agent Section/assets/css/agent-login.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>

<body>
    <!-- Back to homepage button -->
    <a href="#" class="back-btn" id="backToFlights">
        <i class="fas fa-arrow-left"></i> <span> Back to Flight Schedules </span>
    </a>

    <main class="main-container">
        <div class="login-container">
            <div class="logo">
                <img src="../Assets/Logos/logo-tab.png" alt="" class="logo-image" width="160" height="120">
            </div>

            <div class="fields-container">
                <?php
                // echo "<pre>";
                // print_r($_SESSION);
                // echo "</pre>";
                ?>

                <form class="mt-3" id="loginForm" method="POST">
                    <input type="hidden" name="flightid" id="flightid" value="<?= isset($_SESSION['flightid']) ? $_SESSION['flightid'] : '' ?>">

                    <!-- Username input field -->
                    <div class="mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control border-1" id="floatingUsername" name="username" placeholder="Username" required>
                            <label for="floatingUsername">Enter User ID </label>
                        </div>
                    </div>

                    <!-- Password input field -->
                    <div class="mb-1 position-relative">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" aria-describedby="togglePassword" required>
                            <label for="floatingPassword">Password</label>
                            <span id="togglePassword" class="position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                <i class="far fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Forgot password and Remember me options -->
                    <div class="fp-container">
                        <a href="#" class="forgot-password">Forgot your password?</a>
                        <div class="fp-flag">Please contact the admin for assistance.</div>
                    </div>

                    <!-- Placeholder for login messages -->
                    <div id="message-login" class="message-login"></div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 p-3" id="LoginButton" name="login">LOGIN</button>
                </form>
            </div>
        </div>
    </main>
</body>

<?php include "../Agent Section/includes/scripts.php"; ?>


<script>
    $(document).ready(function() {
        $("#backToFlights").click(function(e) {
            e.preventDefault(); // Prevent default anchor behavior

            $.ajax({
                url: "../Agent Section/functions/agent-clear-session.php", // Create this PHP file for handling session cleanup if needed
                type: "POST",
                data: {
                    action: "back"
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        window.location.href = "../Client Section/client-flightsched.php";
                    } else {
                        console.error("Error:", response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                }
            });
        });
    });
</script>

<script>
    $(document).ready(function() {
        $("#backToFlights").click(function(event) {
            event.preventDefault(); // Prevent immediate navigation

            $.ajax({
                url: '../Client Section/functions/unset_flight_session.php', // Adjust path if needed
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.location.href = "../Client Section/client-flightsched.php"; // Redirect after session unset
                    }
                },
                error: function() {
                    window.location.href = "../Client Section/client-flightsched.php"; // Ensure redirection even if AJAX fails
                }
            });
        });
    });
</script>


<script>
    document.addEventListener("DOMContentLoaded", function() {
        const forgotPassword = document.querySelector(".forgot-password");
        const flag = document.querySelector(".fp-flag");

        forgotPassword.addEventListener("click", function(event) {
            event.preventDefault(); // Prevents default link behavior
            event.stopPropagation(); // Prevents immediate closing on click
            flag.style.display = flag.style.display === "block" ? "none" : "block";
        });

        document.addEventListener("click", function(event) {
            if (!forgotPassword.contains(event.target)) {
                flag.style.display = "none"; // Hide when clicking outside
            }
        });
    });
</script>

<script>
    $(document).ready(function() {
        $('#loginForm').on('submit', function(event) {
            event.preventDefault(); // Prevent default form submission

            // Clear previous messages
            $('#message-login').html('');
            $('#LoginButton').removeClass('button-disabled'); // Ensure button is enabled for retries

            const formData = new FormData(this);
            formData.append('login', '1'); // Mark request as login

            console.log("Submitting AJAX request...");

            $.ajax({
                url: '../Agent Section/functions/agentLogin-code.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json', // Expecting JSON response

                success: function(data, status, xhr) {
                    // console.log("AJAX Response:", data);
                    // console.log("Response Status:", status);
                    // console.log("XHR Status Code:", xhr.status);

                    if (data.success) {
                        // console.log("Full Response:", data);
                        // console.log("Account Type:", data.accountType);
                        // console.log("Received Flight ID:", data.flightId || "No Flight ID received");
                        // console.log("Default Password Status:", data.defaultPasswordStat || "Not received");

                        // Extract flight ID, if available
                        let flightid = (data.flightId && data.flightId !== "Not received") ? data.flightId : '';

                        if (data.defaultPasswordStat === "yes") {
                            alert("Default Password Detected. Proceeding to Change Password Page");

                            // Store userType in sessionStorage
                            if (data.userType) {
                                sessionStorage.setItem("userType", data.userType);
                            }

                            // Redirect with userType in the URL
                            window.location.href = "../User/userChangePassword.php?userType=" + encodeURIComponent(data.userType);
                            
                            return; // Stop further execution
                        }
                        

                        // Handle redirection based on account type
                        switch (data.accountType) {
                            case 'agent':
                                console.log("Agent login successful.");

                                if (flightid) {
                                    console.log("Redirecting Agent to booking page.");
                                    window.location.href = '../Agent Section/agent-revisedAddBooking-flight.php';
                                } else {
                                    console.log("Redirecting Agent to dashboard.");
                                    window.location.href = '../Agent Section/agent-dashboard.php';
                                }
                                break;

                            case 'guest':
                                console.log("Guest login successful.");
                                if (flightid) {
                                    console.log("Redirecting Guest to booking page.");
                                    window.location.href = '../Client Section/client-addBooking-flight.php';
                                } else {
                                    console.log("Redirecting Guest to dashboard.");
                                    window.location.href = '../Client Section/client-dashboard.php';
                                }
                                break;

                            case 'employee':
                                console.log("Employee login successful. Redirecting to dashboard.");
                                window.location.href = '../Employee Section/emp-dashboard.php';
                                break;

                            default:
                                console.warn("Unknown account type received:", data.accountType);
                                alert('Unknown account type. Please contact support.');
                        }

                    } else {
                        console.warn("Login failed:", data.message);

                        // Display error message
                        $('#message-login').html(`
                            <div class="alert alert-danger text-center">${data.message}</div>
                        `);

                        // Disable login button if the user is already logged in on another device
                        if (data.message && data.message.trim() === 
                            "You are logged in on another device. Please close from other tab or devices then reload before logging in again!") {
                            console.warn("Disabling login button due to concurrent login.");
                            $('#LoginButton').addClass('button-disabled');
                        }
                    }
                },

                error: function(xhr, status, error) {
                    console.error('AJAX Request Failed');
                    console.error('Status:', status);
                    console.error('XHR Response:', xhr.responseText);
                    console.error('Error:', error);

                    // Display error message
                    $('#message-login').html(`
                        <div class="alert alert-danger">An error occurred. Please try again later.</div>
                    `);

                    // Disable login button
                    $('#LoginButton').addClass('button-disabled');
                }
            });

        });
    });
</script>


<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordField = document.getElementById('floatingPassword');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('far', 'fa-eye'); // Remove line-type eye
            toggleIcon.classList.add('far', 'fa-eye-slash'); // Change to line-type eye-slash
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('far', 'fa-eye-slash'); // Remove line-type eye-slash
            toggleIcon.classList.add('far', 'fa-eye'); // Change back to line-type eye
        }
    });
</script>

</body>

</html>