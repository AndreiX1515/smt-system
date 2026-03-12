<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>

  <!-- Include jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="../Agent Section/assets/css/agent-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Agent Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>
<body>
  <div class="body-container">
    <?php include "../Agent Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <div class="navbar">
        <div class="backbutton-wrapper">
          <div class="back-button-wrapper">
            <a href="../Agent Section/agent-showGuest.php?id=<?= $_SESSION['transaction_number'] ?>" class="back-button-link">
              <i class="fa-solid fa-arrow-left"></i>
            </a>
          </div>

          <div class="page-name-wrapper">
            <h5>Update Guest Information</h5>
          </div>

        </div>
      </div>

      <div class="main-content">
        <?php
          // Check if 'id' is passed in the URL
          if (isset($_GET['id'])) 
          {
            $guestId = htmlspecialchars($_GET['id']);
            $transactionNo = $_SESSION['transaction_number'];
          } 

          $stmt = $conn->prepare("SELECT b.flightId, f.flightDepartureDate as departureDate FROM booking b
                                  JOIN flight f ON b.flightId = f.flightId WHERE transactNo = ?");
          $stmt->bind_param("s", $transactionNo);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($row = $result->fetch_assoc()) 
          {
            $flightdate = $row['departureDate'];
          }
        ?>
        <?php if(isset($_SESSION['status'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <strong>Hey!</strong> <?= $_SESSION['status']; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <?php 
          unset($_SESSION['status']);
          endif;
        ?>
        <div class="addguest-wrapper">
          <div class="wrapper-body">
            <form action="../Agent Section/functions/agent-updateGuestInfo-code.php" id="guestForm" method="POST">
              <div class="card guest-form mb-3">
                <div class="card-header bg-secondary text-white d-flex flex-row justify-content-between align-items-center">
                  <h5 class="font-weight-bold mt-1">Guest Information</h5>
                </div>
                <div class="card-body px-5">
                  <?php
                    $sql1 = $conn->prepare("SELECT * FROM guest WHERE guestId = ?");
                    $sql1->bind_param("i", $guestId);
                    $sql1->execute();
                    $result = $sql1->get_result();
                    if ($row = $result->fetch_assoc()) 
                    { ?>
                      <div class="header-container d-flex flex-row w-100 mb-3 ">
                        <h5 class="card-title bg-primary text-white p-3 w-100">Personal Information</h5>
                        <input type="hidden" name="guestId" value="<?php echo $row['guestId']; ?>" readonly>
                        <input type="hidden" name="transactNo" value="<?php echo $row['transactNo']; ?>" readonly>
                      </div>

                      <!--Guest Name Input Fields-->
                      <div class="row mb-3">
                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="fName">First Name <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="fName" class="form-control" placeholder="Enter First Name" value="<?php echo $row['fName']; ?>" required>
                            <span id="fNameError" class="text-danger"></span> <!-- Error message for First Name -->
                          </div>
                        </div>

                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="lName">Last Name <span class="text-danger fw-bold">*</span> </label>
                            <input type="text" name="lName" class="form-control" placeholder="Enter Last Name" value="<?php echo $row['lName']; ?>" required>
                            <span id="lNameError" class="text-danger"></span> <!-- Error message for Last Name -->
                          </div>
                        </div>

                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="mName">Middle Name <span class="text-danger fw-bold">write N/A if none</span></label>
                            <input type="text" name="mName" class="form-control" placeholder="Enter Middle Name" value="<?php echo $row['mName']; ?>" required>
                            <span id="mNameError" class="text-danger"></span> <!-- Error message for Middle Name -->
                          </div>
                        </div>

                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="suffix">Suffix <span class="text-danger fw-bold">*</span></label>
                            <select class="form-control" name="suffix" required>
                              <option disabled <?php echo (!isset($row['suffix']) || $row['suffix'] == '') ? 'selected' : ''; ?>>Select Suffix</option>
                              <option value="N/A" <?php echo ($row['suffix'] == 'N/A') ? 'selected' : ''; ?>>None</option>
                              <option value="Jr." <?php echo ($row['suffix'] == 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                              <option value="Sr." <?php echo ($row['suffix'] == 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                              <option value="II" <?php echo ($row['suffix'] == 'II') ? 'selected' : ''; ?>>II</option>
                              <option value="III" <?php echo ($row['suffix'] == 'III') ? 'selected' : ''; ?>>III</option>
                              <option value="IV" <?php echo ($row['suffix'] == 'IV') ? 'selected' : ''; ?>>IV</option>
                              <option value="V" <?php echo ($row['suffix'] == 'V') ? 'selected' : ''; ?>>V</option>
                            </select>
                            <span id="suffixError" class="text-danger"></span> <!-- Error message for Suffix -->
                          </div>
                        </div>
                      </div>

                      <!--Guest Birthdate, Age, Sex, and Nationality-->
                      <div class="row mb-3">
                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="birthdate">Birthdate <span class="text-danger fw-bold">*</span> </label>
                            <input type="date" name="birthdate" class="form-control" value="<?php echo $row['birthdate']; ?>" required>
                            <span id="birthdateError" class="text-danger"></span> <!-- Error message for Birthdate -->
                          </div>
                        </div>

                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="age">Age <span class="text-danger fw-bold">*</span> <span id="infant"></span></label>
                            <input type="number" name="age" class="form-control" placeholder="Age" value="<?php echo $row['age']; ?>" readonly>
                            <span id="ageError" class="text-danger"></span> <!-- Error message for Age -->
                          </div>
                        </div>

                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="sex">Sex <span class="text-danger fw-bold">*</span> </label>
                            <select class="form-control" name="sex" required>
                              <option disabled <?php echo (!isset($row['sex']) || $row['sex'] == '') ? 'selected' : ''; ?>>Select Sex</option>
                              <option value="Male" <?php echo (isset($row['sex']) && $row['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                              <option value="Female" <?php echo (isset($row['sex']) && $row['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                            <span id="sexError" class="text-danger"></span> <!-- Error message for Sex -->
                          </div>
                        </div>
                
                        <div class="col-md-3">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="nationality">Nationality <span class="text-danger fw-bold">*</span> </label>
                            <input type="text" class="form-control" name="nationality" list="nationality" placeholder="Enter Nationality" value="<?php echo $row['nationality']; ?>" required>
                            <datalist id="nationality">
                              <option value="Afghan">Afghan</option>
                              <option value="Albanian">Albanian</option>
                              <option value="Algerian">Algerian</option>
                              <option value="American">American</option>
                              <option value="Andorran">Andorran</option>
                              <option value="Angolan">Angolan</option>
                              <option value="Antiguan">Antiguan</option>
                              <option value="Argentine">Argentine</option>
                              <option value="Armenian">Armenian</option>
                              <option value="Australian">Australian</option>
                              <option value="Austrian">Austrian</option>
                              <option value="Azerbaijani">Azerbaijani</option>
                              <option value="Bahaman">Bahaman</option>
                              <option value="Bahraini">Bahraini</option>
                              <option value="Bangladeshi">Bangladeshi</option>
                              <option value="Barbadian">Barbadian</option>
                              <option value="Bashkir">Bashkir</option>
                              <option value="Belarusian">Belarusian</option>
                              <option value="Belgian">Belgian</option>
                              <option value="Belizean">Belizean</option>
                              <option value="Beninese">Beninese</option>
                              <option value="Bhutanese">Bhutanese</option>
                              <option value="Bolivian">Bolivian</option>
                              <option value="Bosnian">Bosnian</option>
                              <option value="Brazilian">Brazilian</option>
                              <option value="Bruneian">Bruneian</option>
                              <option value="Bulgarian">Bulgarian</option>
                              <option value="Burkinabe">Burkinabe</option>
                              <option value="Burundian">Burundian</option>
                              <option value="Cabo Verdean">Cabo Verdean</option>
                              <option value="Cambodian">Cambodian</option>
                              <option value="Cameroonian">Cameroonian</option>
                              <option value="Canadian">Canadian</option>
                              <option value="Central African">Central African</option>
                              <option value="Chadian">Chadian</option>
                              <option value="Chilean">Chilean</option>
                              <option value="Chinese">Chinese</option>
                              <option value="Colombian">Colombian</option>
                              <option value="Comoran">Comoran</option>
                              <option value="Congolese">Congolese</option>
                              <option value="Costa Rican">Costa Rican</option>
                              <option value="Croatian">Croatian</option>
                              <option value="Cuban">Cuban</option>
                              <option value="Cypriot">Cypriot</option>
                              <option value="Czech">Czech</option>
                              <option value="Danish">Danish</option>
                              <option value="Djiboutian">Djiboutian</option>
                              <option value="Dominican">Dominican</option>
                              <option value="Dutch">Dutch</option>
                              <option value="East Timorese">East Timorese</option>
                              <option value="Ecuadorean">Ecuadorean</option>
                              <option value="Egyptian">Egyptian</option>
                              <option value="Emirati">Emirati</option>
                              <option value="Equatorial Guinean">Equatorial Guinean</option>
                              <option value="Eritrean">Eritrean</option>
                              <option value="Estonian">Estonian</option>
                              <option value="Eswatini">Eswatini</option>
                              <option value="Ethiopian">Ethiopian</option>
                              <option value="Fijian">Fijian</option>
                              <option value="Filipino">Filipino</option>
                              <option value="Finnish">Finnish</option>
                              <option value="French">French</option>
                              <option value="Gabonese">Gabonese</option>
                              <option value="Gambian">Gambian</option>
                              <option value="Georgian">Georgian</option>
                              <option value="German">German</option>
                              <option value="Ghanaian">Ghanaian</option>
                              <option value="Greek">Greek</option>
                              <option value="Grenadian">Grenadian</option>
                              <option value="Guatemalan">Guatemalan</option>
                              <option value="Guinea-Bissauan">Guinea-Bissauan</option>
                              <option value="Guinean">Guinean</option>
                              <option value="Guyanese">Guyanese</option>
                              <option value="Haitian">Haitian</option>
                              <option value="Honduran">Honduran</option>
                              <option value="Hungarian">Hungarian</option>
                              <option value="Icelander">Icelander</option>
                              <option value="Indian">Indian</option>
                              <option value="Indonesian">Indonesian</option>
                              <option value="Iranian">Iranian</option>
                              <option value="Iraqi">Iraqi</option>
                              <option value="Irish">Irish</option>
                              <option value="Israeli">Israeli</option>
                              <option value="Italian">Italian</option>
                              <option value="Ivorian">Ivorian</option>
                              <option value="Jamaican">Jamaican</option>
                              <option value="Japanese">Japanese</option>
                              <option value="Jordanian">Jordanian</option>
                              <option value="Kazakhstani">Kazakhstani</option>
                              <option value="Kenyan">Kenyan</option>
                              <option value="Kuwaiti">Kuwaiti</option>
                              <option value="Kyrgyz">Kyrgyz</option>
                              <option value="Laotian">Laotian</option>
                              <option value="Latvian">Latvian</option>
                              <option value="Lebanese">Lebanese</option>
                              <option value="Liberian">Liberian</option>
                              <option value="Libyan">Libyan</option>
                              <option value="Liechtenstein citizen">Liechtenstein citizen</option>
                              <option value="Lithuanian">Lithuanian</option>
                              <option value="Luxembourger">Luxembourger</option>
                              <option value="Malagasy">Malagasy</option>
                              <option value="Malawian">Malawian</option>
                              <option value="Malaysian">Malaysian</option>
                              <option value="Maldivian">Maldivian</option>
                              <option value="Malian">Malian</option>
                              <option value="Maltese">Maltese</option>
                              <option value="Marshallese">Marshallese</option>
                              <option value="Mauritanian">Mauritanian</option>
                              <option value="Mauritian">Mauritian</option>
                              <option value="Mexican">Mexican</option>
                              <option value="Micronesian">Micronesian</option>
                              <option value="Moldovan">Moldovan</option>
                              <option value="Monacan">Monacan</option>
                              <option value="Mongolian">Mongolian</option>
                              <option value="Montenegrin">Montenegrin</option>
                              <option value="Moroccan">Moroccan</option>
                              <option value="Mozambican">Mozambican</option>
                              <option value="Myanmar">Myanmar</option>
                              <option value="Namibian">Namibian</option>
                              <option value="Nauruan">Nauruan</option>
                              <option value="Nepali">Nepali</option>
                              <option value="New Zealander">New Zealander</option>
                              <option value="Nicaraguan">Nicaraguan</option>
                              <option value="Nigerien">Nigerien</option>
                              <option value="Nigerian">Nigerian</option>
                              <option value="North Korean">North Korean</option>
                              <option value="North Macedonian">North Macedonian</option>
                              <option value="Norwegian">Norwegian</option>
                              <option value="Omani">Omani</option>
                              <option value="Pakistani">Pakistani</option>
                              <option value="Palauan">Palauan</option>
                              <option value="Panamanian">Panamanian</option>
                              <option value="Papua New Guinean">Papua New Guinean</option>
                              <option value="Paraguayan">Paraguayan</option>
                              <option value="Peruvian">Peruvian</option>
                              <option value="Polish">Polish</option>
                              <option value="Portuguese">Portuguese</option>
                              <option value="Qatari">Qatari</option>
                              <option value="Romanian">Romanian</option>
                              <option value="Russian">Russian</option>
                              <option value="Rwandan">Rwandan</option>
                              <option value="Saint Kitts">Saint Kitts</option>
                              <option value="and Nevis">and Nevis</option>
                              <option value="Saint Lucian">Saint Lucian</option>
                              <option value="Salvadoran">Salvadoran</option>
                              <option value="Samoan">Samoan</option>
                              <option value="San Marinese">San Marinese</option>
                              <option value="Sao Tomean">Sao Tomean</option>
                              <option value="Saudi Arabian">Saudi Arabian</option>
                              <option value="Scottish">Scottish</option>
                              <option value="Senegalese">Senegalese</option>
                              <option value="Serbian">Serbian</option>
                              <option value="Seychellois">Seychellois</option>
                              <option value="Sierra Leonean">Sierra Leonean</option>
                              <option value="Singaporean">Singaporean</option>
                              <option value="Slovak">Slovak</option>
                              <option value="Slovenian">Slovenian</option>
                              <option value="Solomon Islander">Solomon Islander</option>
                              <option value="Somali">Somali</option>
                              <option value="South African">South African</option>
                              <option value="South Korean">South Korean</option>
                              <option value="Spanish">Spanish</option>
                              <option value="Sri Lankan">Sri Lankan</option>
                              <option value="Sudanese">Sudanese</option>
                              <option value="Surinamese">Surinamese</option>
                              <option value="Swedish">Swedish</option>
                              <option value="Swiss">Swiss</option>
                              <option value="Syrian">Syrian</option>
                              <option value="Taiwanese">Taiwanese</option>
                              <option value="Tajik">Tajik</option>
                              <option value="Tanzanian">Tanzanian</option>
                              <option value="Thai">Thai</option>
                              <option value="Togolese">Togolese</option>
                              <option value="Tongan">Tongan</option>
                              <option value="Trinidadian">Trinidadian</option>
                              <option value="Tobagonian">Tobagonian</option>
                              <option value="Tunisian">Tunisian</option>
                              <option value="Turkish">Turkish</option>
                              <option value="Turkmen">Turkmen</option>
                              <option value="Tuvaluan">Tuvaluan</option>
                              <option value="Ugandan">Ugandan</option>
                              <option value="Ukrainian">Ukrainian</option>
                              <option value="Uruguayan">Uruguayan</option>
                              <option value="Uzbek">Uzbek</option>
                              <option value="Venezuelan">Venezuelan</option>
                              <option value="Vietnamese">Vietnamese</option>
                              <option value="Welsh">Welsh</option>
                              <option value="Yemeni">Yemeni</option>
                              <option value="Zambian">Zambian</option>
                              <option value="Zimbabwean">Zimbabwean</option>
                            </datalist>
                            <span id="nationalityError" class="text-danger"></span> <!-- Error message for Nationality -->
                          </div>
                        </div>
                      </div>

                      <!--Guest Passport No, and Expiration-->
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="passportNo">Passport No. <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="passportNo" class="form-control" placeholder="Enter Passport No" value="<?php echo $row['passportNo']; ?>" required>
                            <span id="passportNoError" class="text-danger"></span> <!-- Error message for Passport No -->
                          </div>
                        </div>

                        <div class="columns col-md-4">
                          <div class="form-group">
                            <label for="passportIssued">Date Issued: <span class="text-danger fw-bold">*</span></label>
                            <input type="date" name="passportIssued" class="form-control" value="<?php echo $row['passportIssuedDate']; ?>" required>
                            <span id="passportIssuedError" class="text-danger"></span> <!-- Error message for Passport No -->
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="passportExp">Date of Expiration: <span class="text-danger fw-bold">*</span> <span id="expPassport" class="text-danger"></span></label>
                            <input type="date" name="passportExp" class="form-control" value="<?php echo $row['passportExp']; ?>" required>
                            <span id="passportExpError" class="text-danger"></span> <!-- Error message for Passport Exp -->
                          </div>
                        </div>
                      </div>

                      <!-- Guest Contact Information -->
                      <div class="header-container d-flex flex-row w-100 mb-3 ">
                        <h5 class="card-title bg-primary text-white p-3 w-100">Contact Information</h5>
                      </div>

                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group mb-4">
                            <label class="mb-2" for="contactNo">Contact No. <span class="text-danger fw-bold">*</span></label>
                            <div class="input-group">
                              <select name="countryCode" class="form-select" value="<?php echo $row['countryCode']; ?>" required>
                                <option disabled selected>Country Code</option>
                                <option value="+93" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+93') ? 'selected' : ''; ?>>Afghanistan (+93)</option>
                                <option value="+355" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+355') ? 'selected' : ''; ?>>Albania (+355)</option>
                                <option value="+213" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+213') ? 'selected' : ''; ?>>Algeria (+213)</option>
                                <option value="+376" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+376') ? 'selected' : ''; ?>>Andorra (+376)</option>
                                <option value="+244" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+244') ? 'selected' : ''; ?>>Angola (+244)</option>
                                <option value="+1-268" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-268') ? 'selected' : ''; ?>>Antigua and Barbuda (+1-268)</option>
                                <option value="+54" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+54') ? 'selected' : ''; ?>>Argentina (+54)</option>
                                <option value="+374" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+374') ? 'selected' : ''; ?>>Armenia (+374)</option>
                                <option value="+61" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+61') ? 'selected' : ''; ?>>Australia (+61)</option>
                                <option value="+43" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+43') ? 'selected' : ''; ?>>Austria (+43)</option>
                                <option value="+994" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+994') ? 'selected' : ''; ?>>Azerbaijan (+994)</option>
                                <option value="+1-242" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-242') ? 'selected' : ''; ?>>Bahamas (+1-242)</option>
                                <option value="+973" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+973') ? 'selected' : ''; ?>>Bahrain (+973)</option>
                                <option value="+880" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+880') ? 'selected' : ''; ?>>Bangladesh (+880)</option>
                                <option value="+1-246" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-246') ? 'selected' : ''; ?>>Barbados (+1-246)</option>
                                <option value="+375" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+375') ? 'selected' : ''; ?>>Belarus (+375)</option>
                                <option value="+32" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+32') ? 'selected' : ''; ?>>Belgium (+32)</option>
                                <option value="+501" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+501') ? 'selected' : ''; ?>>Belize (+501)</option>
                                <option value="+229" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+229') ? 'selected' : ''; ?>>Benin (+229)</option>
                                <option value="+975" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+975') ? 'selected' : ''; ?>>Bhutan (+975)</option>
                                <option value="+591" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+591') ? 'selected' : ''; ?>>Bolivia (+591)</option>
                                <option value="+387" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+387') ? 'selected' : ''; ?>>Bosnia and Herzegovina (+387)</option>
                                <option value="+267" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+267') ? 'selected' : ''; ?>>Botswana (+267)</option>
                                <option value="+55" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+55') ? 'selected' : ''; ?>>Brazil (+55)</option>
                                <option value="+673" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+673') ? 'selected' : ''; ?>>Brunei (+673)</option>
                                <option value="+359" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+359') ? 'selected' : ''; ?>>Bulgaria (+359)</option>
                                <option value="+226" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+226') ? 'selected' : ''; ?>>Burkina Faso (+226)</option>
                                <option value="+257" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+257') ? 'selected' : ''; ?>>Burundi (+257)</option>
                                <option value="+238" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+238') ? 'selected' : ''; ?>>Cabo Verde (+238)</option>
                                <option value="+855" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+855') ? 'selected' : ''; ?>>Cambodia (+855)</option>
                                <option value="+237" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+237') ? 'selected' : ''; ?>>Cameroon (+237)</option>
                                <option value="+1" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1') ? 'selected' : ''; ?>>Canada (+1)</option>
                                <option value="+236" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+236') ? 'selected' : ''; ?>>Central African Republic (+236)</option>
                                <option value="+235" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+235') ? 'selected' : ''; ?>>Chad (+235)</option>
                                <option value="+56" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+56') ? 'selected' : ''; ?>>Chile (+56)</option>
                                <option value="+86" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+86') ? 'selected' : ''; ?>>China (+86)</option>
                                <option value="+57" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+57') ? 'selected' : ''; ?>>Colombia (+57)</option>
                                <option value="+269" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+269') ? 'selected' : ''; ?>>Comoros (+269)</option>
                                <option value="+243" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+243') ? 'selected' : ''; ?>>Congo, Democratic Republic of the (+243)</option>
                                <option value="+242" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+242') ? 'selected' : ''; ?>>Congo, Republic of the (+242)</option>
                                <option value="+506" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+506') ? 'selected' : ''; ?>>Costa Rica (+506)</option>
                                <option value="+385" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+385') ? 'selected' : ''; ?>>Croatia (+385)</option>
                                <option value="+53" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+53') ? 'selected' : ''; ?>>Cuba (+53)</option>
                                <option value="+357" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+357') ? 'selected' : ''; ?>>Cyprus (+357)</option>
                                <option value="+420" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+420') ? 'selected' : ''; ?>>Czech Republic (+420)</option>
                                <option value="+45" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+45') ? 'selected' : ''; ?>>🇩🇰 Denmark (+45)</option>
                                <option value="+253" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+253') ? 'selected' : ''; ?>>🇩🇯 Djibouti (+253)</option>
                                <option value="+1-767" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-767') ? 'selected' : ''; ?>>🇩🇲 Dominica (+1-767)</option>
                                <option value="+1-809" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-809') ? 'selected' : ''; ?>>🇩🇴 Dominican Republic (+1-809)</option>
                                <option value="+593" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+593') ? 'selected' : ''; ?>>Ecuador (+593)</option>
                                <option value="+20" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+20') ? 'selected' : ''; ?>>Egypt (+20)</option>
                                <option value="+503" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+503') ? 'selected' : ''; ?>>El Salvador (+503)</option>
                                <option value="+240" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+240') ? 'selected' : ''; ?>>Equatorial Guinea (+240)</option>
                                <option value="+291" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+291') ? 'selected' : ''; ?>>Eritrea (+291)</option>
                                <option value="+372" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+372') ? 'selected' : ''; ?>>Estonia (+372)</option>
                                <option value="+268" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+268') ? 'selected' : ''; ?>>Eswatini (+268)</option>
                                <option value="+251" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+251') ? 'selected' : ''; ?>>Ethiopia (+251)</option>
                                <option value="+679" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+679') ? 'selected' : ''; ?>>Fiji (+679)</option>
                                <option value="+358" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+358') ? 'selected' : ''; ?>>Finland (+358)</option>
                                <option value="+33" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+33') ? 'selected' : ''; ?>>France (+33)</option>
                                <option value="+241" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+241') ? 'selected' : ''; ?>>Gabon (+241)</option>
                                <option value="+220" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+220') ? 'selected' : ''; ?>>Gambia (+220)</option>
                                <option value="+995" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+995') ? 'selected' : ''; ?>>Georgia (+995)</option>
                                <option value="+49" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+49') ? 'selected' : ''; ?>>Germany (+49)</option>
                                <option value="+233" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+233') ? 'selected' : ''; ?>>Ghana (+233)</option>
                                <option value="+30" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+30') ? 'selected' : ''; ?>>Greece (+30)</option>
                                <option value="+1-473" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-473') ? 'selected' : ''; ?>>Grenada (+1-473)</option>
                                <option value="+502" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+502') ? 'selected' : ''; ?>>Guatemala (+502)</option>
                                <option value="+224" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+224') ? 'selected' : ''; ?>>Guinea (+224)</option>
                                <option value="+245" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+245') ? 'selected' : ''; ?>>Guinea-Bissau (+245)</option>
                                <option value="+592" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+592') ? 'selected' : ''; ?>>Guyana (+592)</option>
                                <option value="+509" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+509') ? 'selected' : ''; ?>>Haiti (+509)</option>
                                <option value="+504" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+504') ? 'selected' : ''; ?>>Honduras (+504)</option>
                                <option value="+36" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+36') ? 'selected' : ''; ?>>Hungary (+36)</option>
                                <option value="+354" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+354') ? 'selected' : ''; ?>>Iceland (+354)</option>
                                <option value="+91" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+91') ? 'selected' : ''; ?>>India (+91)</option>
                                <option value="+62" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+62') ? 'selected' : ''; ?>>Indonesia (+62)</option>
                                <option value="+98" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+98') ? 'selected' : ''; ?>>Iran (+98)</option>
                                <option value="+964" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+964') ? 'selected' : ''; ?>>Iraq (+964)</option>
                                <option value="+353" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+353') ? 'selected' : ''; ?>>Ireland (+353)</option>
                                <option value="+972" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+972') ? 'selected' : ''; ?>>Israel (+972)</option>
                                <option value="+39" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+39') ? 'selected' : ''; ?>>Italy (+39)</option>
                                <option value="+225" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+225') ? 'selected' : ''; ?>>Ivory Coast (+225)</option>
                                <option value="+81" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+81') ? 'selected' : ''; ?>>Japan (+81)</option>
                                <option value="+962" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+962') ? 'selected' : ''; ?>>Jordan (+962)</option>
                                <option value="+7" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+7') ? 'selected' : ''; ?>>Kazakhstan (+7)</option>
                                <option value="+254" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+254') ? 'selected' : ''; ?>>Kenya (+254)</option>
                                <option value="+686" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+686') ? 'selected' : ''; ?>>Kiribati (+686)</option>
                                <option value="+965" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+965') ? 'selected' : ''; ?>>Kuwait (+965)</option>
                                <option value="+996" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+996') ? 'selected' : ''; ?>>Kyrgyzstan (+996)</option>
                                <option value="+856" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+856') ? 'selected' : ''; ?>>Laos (+856)</option>
                                <option value="+371" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+371') ? 'selected' : ''; ?>>Latvia (+371)</option>
                                <option value="+961" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+961') ? 'selected' : ''; ?>>Lebanon (+961)</option>
                                <option value="+266" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+266') ? 'selected' : ''; ?>>Lesotho (+266)</option>
                                <option value="+231" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+231') ? 'selected' : ''; ?>>Liberia (+231)</option>
                                <option value="+218" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+218') ? 'selected' : ''; ?>>Libya (+218)</option>
                                <option value="+423" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+423') ? 'selected' : ''; ?>>Liechtenstein (+423)</option>
                                <option value="+370" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+370') ? 'selected' : ''; ?>>Lithuania (+370)</option>
                                <option value="+352" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+352') ? 'selected' : ''; ?>>Luxembourg (+352)</option>
                                <option value="+261" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+261') ? 'selected' : ''; ?>>Madagascar (+261)</option>
                                <option value="+265" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+265') ? 'selected' : ''; ?>>Malawi (+265)</option>
                                <option value="+60" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+60') ? 'selected' : ''; ?>>Malaysia (+60)</option>
                                <option value="+960" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+960') ? 'selected' : ''; ?>>Maldives (+960)</option>
                                <option value="+223" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+223') ? 'selected' : ''; ?>>Mali (+223)</option>
                                <option value="+356" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+356') ? 'selected' : ''; ?>>Malta (+356)</option>
                                <option value="+692" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+692') ? 'selected' : ''; ?>>Marshall Islands (+692)</option>
                                <option value="+596" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+596') ? 'selected' : ''; ?>>Martinique (+596)</option>
                                <option value="+222" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+222') ? 'selected' : ''; ?>>Morocco (+222)</option>
                                <option value="+258" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+258') ? 'selected' : ''; ?>>Mozambique (+258)</option>
                                <option value="+95" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+95') ? 'selected' : ''; ?>>Myanmar (+95)</option>
                                <option value="+264" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+264') ? 'selected' : ''; ?>>Namibia (+264)</option>
                                <option value="+674" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+674') ? 'selected' : ''; ?>>Nauru (+674)</option>
                                <option value="+977" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+977') ? 'selected' : ''; ?>>Nepal (+977)</option>
                                <option value="+31" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+31') ? 'selected' : ''; ?>>Netherlands (+31)</option>
                                <option value="+599" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+599') ? 'selected' : ''; ?>>Netherlands Antilles (+599)</option>
                                <option value="+64" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+64') ? 'selected' : ''; ?>>New Zealand (+64)</option>
                                <option value="+505" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+505') ? 'selected' : ''; ?>>Nicaragua (+505)</option>
                                <option value="+227" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+227') ? 'selected' : ''; ?>>Niger (+227)</option>
                                <option value="+234" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+234') ? 'selected' : ''; ?>>Nigeria (+234)</option>
                                <option value="+683" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+683') ? 'selected' : ''; ?>>Niue (+683)</option>
                                <option value="+672" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+672') ? 'selected' : ''; ?>>Norfolk Island (+672)</option>
                                <option value="+850" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+850') ? 'selected' : ''; ?>>North Korea (+850)</option>
                                <option value="+1-670" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-670') ? 'selected' : ''; ?>>Northern Mariana Islands (+1-670)</option>
                                <option value="+47" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+47') ? 'selected' : ''; ?>>Norway (+47)</option>
                                <option value="+968" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+968') ? 'selected' : ''; ?>>Oman (+968)</option>
                                <option value="+92" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+92') ? 'selected' : ''; ?>>Pakistan (+92)</option>
                                <option value="+680" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+680') ? 'selected' : ''; ?>>Palau (+680)</option>
                                <option value="+507" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+507') ? 'selected' : ''; ?>>Panama (+507)</option>
                                <option value="+675" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+675') ? 'selected' : ''; ?>>Papua New Guinea (+675)</option>
                                <option value="+595" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+595') ? 'selected' : ''; ?>>Paraguay (+595)</option>
                                <option value="+51" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+51') ? 'selected' : ''; ?>>Peru (+51)</option>
                                <option value="+63" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+63') ? 'selected' : ''; ?>>Philippines (+63)</option>
                                <option value="+48" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+48') ? 'selected' : ''; ?>>Poland (+48)</option>
                                <option value="+351" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+351') ? 'selected' : ''; ?>>Portugal (+351)</option>
                                <option value="+974" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+974') ? 'selected' : ''; ?>>Qatar (+974)</option>
                                <option value="+40" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+40') ? 'selected' : ''; ?>>Romania (+40)</option>
                                <option value="+7" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+7') ? 'selected' : ''; ?>>Russia (+7)</option>
                                <option value="+250" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+250') ? 'selected' : ''; ?>>Rwanda (+250)</option>
                                <option value="+508" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+508') ? 'selected' : ''; ?>>Saint Barthélemy (+508)</option>
                                <option value="+1-869" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-869') ? 'selected' : ''; ?>>Saint Kitts and Nevis (+1-869)</option>
                                <option value="+1-758" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-758') ? 'selected' : ''; ?>>Saint Lucia (+1-758)</option>
                                <option value="+590" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+590') ? 'selected' : ''; ?>>Saint Martin (+590)</option>
                                <option value="+1-345" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-345') ? 'selected' : ''; ?>>Cayman Islands (+1-345)</option>
                                <option value="+239" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+239') ? 'selected' : ''; ?>>São Tomé and Príncipe (+239)</option>
                                <option value="+966" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+966') ? 'selected' : ''; ?>>Saudi Arabia (+966)</option>
                                <option value="+221" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+221') ? 'selected' : ''; ?>>Senegal (+221)</option>
                                <option value="+381" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+381') ? 'selected' : ''; ?>>Serbia (+381)</option>
                                <option value="+248" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+248') ? 'selected' : ''; ?>>Seychelles (+248)</option>
                                <option value="+232" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+232') ? 'selected' : ''; ?>>Sierra Leone (+232)</option>
                                <option value="+65" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+65') ? 'selected' : ''; ?>>Singapore (+65)</option>
                                <option value="+421" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+421') ? 'selected' : ''; ?>>Slovakia (+421)</option>
                                <option value="+386" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+386') ? 'selected' : ''; ?>>Slovenia (+386)</option>
                                <option value="+677" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+677') ? 'selected' : ''; ?>>Solomon Islands (+677)</option>
                                <option value="+252" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+252') ? 'selected' : ''; ?>>Somalia (+252)</option>
                                <option value="+27" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+27') ? 'selected' : ''; ?>>South Africa (+27)</option>
                                <option value="+82" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+82') ? 'selected' : ''; ?>>South Korea (+82)</option>
                                <option value="+211" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+211') ? 'selected' : ''; ?>>South Sudan (+211)</option>
                                <option value="+34" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+34') ? 'selected' : ''; ?>>Spain (+34)</option>
                                <option value="+94" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+94') ? 'selected' : ''; ?>>Sri Lanka (+94)</option>
                                <option value="+249" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+249') ? 'selected' : ''; ?>>Sudan (+249)</option>
                                <option value="+597" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+597') ? 'selected' : ''; ?>>Suriname (+597)</option>
                                <option value="+268" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+268') ? 'selected' : ''; ?>>Swaziland (+268)</option>
                                <option value="+46" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+46') ? 'selected' : ''; ?>>Sweden (+46)</option>
                                <option value="+41" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+41') ? 'selected' : ''; ?>>Switzerland (+41)</option>
                                <option value="+963" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+963') ? 'selected' : ''; ?>>Syria (+963)</option>
                                <option value="+886" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+886') ? 'selected' : ''; ?>>Taiwan (+886)</option>
                                <option value="+992" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+992') ? 'selected' : ''; ?>>Tajikistan (+992)</option>
                                <option value="+255" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+255') ? 'selected' : ''; ?>>Tanzania (+255)</option>
                                <option value="+66" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+66') ? 'selected' : ''; ?>>Thailand (+66)</option>
                                <option value="+670" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+670') ? 'selected' : ''; ?>>Timor-Leste (+670)</option>
                                <option value="+228" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+228') ? 'selected' : ''; ?>>Togo (+228)</option>
                                <option value="+676" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+676') ? 'selected' : ''; ?>>Tonga (+676)</option>
                                <option value="+1-868" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-868') ? 'selected' : ''; ?>>Trinidad and Tobago (+1-868)</option>
                                <option value="+216" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+216') ? 'selected' : ''; ?>>Tunisia (+216)</option>
                                <option value="+90" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+90') ? 'selected' : ''; ?>>Turkey (+90)</option>
                                <option value="+993" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+993') ? 'selected' : ''; ?>>Turkmenistan (+993)</option>
                                <option value="+1-649" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+1-649') ? 'selected' : ''; ?>>Turks and Caicos Islands (+1-649)</option>
                                <option value="+688" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+688') ? 'selected' : ''; ?>>Vanuatu (+688)</option>
                                <option value="+39" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+39') ? 'selected' : ''; ?>>Vatican City (+39)</option>
                                <option value="+58" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+58') ? 'selected' : ''; ?>>Venezuela (+58)</option>
                                <option value="+84" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+84') ? 'selected' : ''; ?>>Vietnam (+84)</option>
                                <option value="+681" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+681') ? 'selected' : ''; ?>>Wallis and Futuna (+681)</option>
                                <option value="+967" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+967') ? 'selected' : ''; ?>>Yemen (+967)</option>
                                <option value="+260" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+260') ? 'selected' : ''; ?>>Zambia (+260)</option>
                                <option value="+263" <?php echo (isset($row['countryCode']) && $row['countryCode'] == '+263') ? 'selected' : ''; ?>>Zimbabwe (+263)</option>
                              </select>
                              <input type="tel" class="form-control" id="contactNo" name="contactNo" placeholder="Contact Number" value="<?php echo $row['contactNo']; ?>" required>
                            </div>
                            <span id="contactNoError" class="text-danger"></span> <!-- Error message for Contact No -->
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="form-group mb-4">
                            <label class="mb-2" for="2ndcontactNo">Other Contact No.</label>
                            <div class="input-group">
                              <select name="2ndcountryCode" class="form-select" value="<?php echo $row['countryCode2']; ?>">
                                <option disabled selected>Country Code</option>
                                <option value="+93" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+93') ? 'selected' : ''; ?>>Afghanistan (+93)</option>
                                <option value="+355" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+355') ? 'selected' : ''; ?>>Albania (+355)</option>
                                <option value="+213" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+213') ? 'selected' : ''; ?>>Algeria (+213)</option>
                                <option value="+376" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+376') ? 'selected' : ''; ?>>Andorra (+376)</option>
                                <option value="+244" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+244') ? 'selected' : ''; ?>>Angola (+244)</option>
                                <option value="+1-268" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-268') ? 'selected' : ''; ?>>Antigua and Barbuda (+1-268)</option>
                                <option value="+54" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+54') ? 'selected' : ''; ?>>Argentina (+54)</option>
                                <option value="+374" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+374') ? 'selected' : ''; ?>>Armenia (+374)</option>
                                <option value="+61" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+61') ? 'selected' : ''; ?>>Australia (+61)</option>
                                <option value="+43" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+43') ? 'selected' : ''; ?>>Austria (+43)</option>
                                <option value="+994" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+994') ? 'selected' : ''; ?>>Azerbaijan (+994)</option>
                                <option value="+1-242" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-242') ? 'selected' : ''; ?>>Bahamas (+1-242)</option>
                                <option value="+973" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+973') ? 'selected' : ''; ?>>Bahrain (+973)</option>
                                <option value="+880" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+880') ? 'selected' : ''; ?>>Bangladesh (+880)</option>
                                <option value="+1-246" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-246') ? 'selected' : ''; ?>>Barbados (+1-246)</option>
                                <option value="+375" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+375') ? 'selected' : ''; ?>>Belarus (+375)</option>
                                <option value="+32" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+32') ? 'selected' : ''; ?>>Belgium (+32)</option>
                                <option value="+501" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+501') ? 'selected' : ''; ?>>Belize (+501)</option>
                                <option value="+229" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+229') ? 'selected' : ''; ?>>Benin (+229)</option>
                                <option value="+975" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+975') ? 'selected' : ''; ?>>Bhutan (+975)</option>
                                <option value="+591" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+591') ? 'selected' : ''; ?>>Bolivia (+591)</option>
                                <option value="+387" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+387') ? 'selected' : ''; ?>>Bosnia and Herzegovina (+387)</option>
                                <option value="+267" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+267') ? 'selected' : ''; ?>>Botswana (+267)</option>
                                <option value="+55" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+55') ? 'selected' : ''; ?>>Brazil (+55)</option>
                                <option value="+673" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+673') ? 'selected' : ''; ?>>Brunei (+673)</option>
                                <option value="+359" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+359') ? 'selected' : ''; ?>>Bulgaria (+359)</option>
                                <option value="+226" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+226') ? 'selected' : ''; ?>>Burkina Faso (+226)</option>
                                <option value="+257" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+257') ? 'selected' : ''; ?>>Burundi (+257)</option>
                                <option value="+238" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+238') ? 'selected' : ''; ?>>Cabo Verde (+238)</option>
                                <option value="+855" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+855') ? 'selected' : ''; ?>>Cambodia (+855)</option>
                                <option value="+237" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+237') ? 'selected' : ''; ?>>Cameroon (+237)</option>
                                <option value="+1" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1') ? 'selected' : ''; ?>>Canada (+1)</option>
                                <option value="+236" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+236') ? 'selected' : ''; ?>>Central African Republic (+236)</option>
                                <option value="+235" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+235') ? 'selected' : ''; ?>>Chad (+235)</option>
                                <option value="+56" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+56') ? 'selected' : ''; ?>>Chile (+56)</option>
                                <option value="+86" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+86') ? 'selected' : ''; ?>>China (+86)</option>
                                <option value="+57" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+57') ? 'selected' : ''; ?>>Colombia (+57)</option>
                                <option value="+269" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+269') ? 'selected' : ''; ?>>Comoros (+269)</option>
                                <option value="+243" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+243') ? 'selected' : ''; ?>>Congo, Democratic Republic of the (+243)</option>
                                <option value="+242" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+242') ? 'selected' : ''; ?>>Congo, Republic of the (+242)</option>
                                <option value="+506" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+506') ? 'selected' : ''; ?>>Costa Rica (+506)</option>
                                <option value="+385" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+385') ? 'selected' : ''; ?>>Croatia (+385)</option>
                                <option value="+53" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+53') ? 'selected' : ''; ?>>Cuba (+53)</option>
                                <option value="+357" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+357') ? 'selected' : ''; ?>>Cyprus (+357)</option>
                                <option value="+420" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+420') ? 'selected' : ''; ?>>Czech Republic (+420)</option>
                                <option value="+45" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+45') ? 'selected' : ''; ?>>🇩🇰 Denmark (+45)</option>
                                <option value="+253" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+253') ? 'selected' : ''; ?>>🇩🇯 Djibouti (+253)</option>
                                <option value="+1-767" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-767') ? 'selected' : ''; ?>>🇩🇲 Dominica (+1-767)</option>
                                <option value="+1-809" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-809') ? 'selected' : ''; ?>>🇩🇴 Dominican Republic (+1-809)</option>
                                <option value="+593" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+593') ? 'selected' : ''; ?>>Ecuador (+593)</option>
                                <option value="+20" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+20') ? 'selected' : ''; ?>>Egypt (+20)</option>
                                <option value="+503" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+503') ? 'selected' : ''; ?>>El Salvador (+503)</option>
                                <option value="+240" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+240') ? 'selected' : ''; ?>>Equatorial Guinea (+240)</option>
                                <option value="+291" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+291') ? 'selected' : ''; ?>>Eritrea (+291)</option>
                                <option value="+372" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+372') ? 'selected' : ''; ?>>Estonia (+372)</option>
                                <option value="+268" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+268') ? 'selected' : ''; ?>>Eswatini (+268)</option>
                                <option value="+251" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+251') ? 'selected' : ''; ?>>Ethiopia (+251)</option>
                                <option value="+679" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+679') ? 'selected' : ''; ?>>Fiji (+679)</option>
                                <option value="+358" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+358') ? 'selected' : ''; ?>>Finland (+358)</option>
                                <option value="+33" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+33') ? 'selected' : ''; ?>>France (+33)</option>
                                <option value="+241" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+241') ? 'selected' : ''; ?>>Gabon (+241)</option>
                                <option value="+220" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+220') ? 'selected' : ''; ?>>Gambia (+220)</option>
                                <option value="+995" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+995') ? 'selected' : ''; ?>>Georgia (+995)</option>
                                <option value="+49" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+49') ? 'selected' : ''; ?>>Germany (+49)</option>
                                <option value="+233" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+233') ? 'selected' : ''; ?>>Ghana (+233)</option>
                                <option value="+30" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+30') ? 'selected' : ''; ?>>Greece (+30)</option>
                                <option value="+1-473" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-473') ? 'selected' : ''; ?>>Grenada (+1-473)</option>
                                <option value="+502" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+502') ? 'selected' : ''; ?>>Guatemala (+502)</option>
                                <option value="+224" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+224') ? 'selected' : ''; ?>>Guinea (+224)</option>
                                <option value="+245" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+245') ? 'selected' : ''; ?>>Guinea-Bissau (+245)</option>
                                <option value="+592" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+592') ? 'selected' : ''; ?>>Guyana (+592)</option>
                                <option value="+509" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+509') ? 'selected' : ''; ?>>Haiti (+509)</option>
                                <option value="+504" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+504') ? 'selected' : ''; ?>>Honduras (+504)</option>
                                <option value="+36" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+36') ? 'selected' : ''; ?>>Hungary (+36)</option>
                                <option value="+354" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+354') ? 'selected' : ''; ?>>Iceland (+354)</option>
                                <option value="+91" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+91') ? 'selected' : ''; ?>>India (+91)</option>
                                <option value="+62" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+62') ? 'selected' : ''; ?>>Indonesia (+62)</option>
                                <option value="+98" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+98') ? 'selected' : ''; ?>>Iran (+98)</option>
                                <option value="+964" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+964') ? 'selected' : ''; ?>>Iraq (+964)</option>
                                <option value="+353" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+353') ? 'selected' : ''; ?>>Ireland (+353)</option>
                                <option value="+972" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+972') ? 'selected' : ''; ?>>Israel (+972)</option>
                                <option value="+39" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+39') ? 'selected' : ''; ?>>Italy (+39)</option>
                                <option value="+225" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+225') ? 'selected' : ''; ?>>Ivory Coast (+225)</option>
                                <option value="+81" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+81') ? 'selected' : ''; ?>>Japan (+81)</option>
                                <option value="+962" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+962') ? 'selected' : ''; ?>>Jordan (+962)</option>
                                <option value="+7" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+7') ? 'selected' : ''; ?>>Kazakhstan (+7)</option>
                                <option value="+254" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+254') ? 'selected' : ''; ?>>Kenya (+254)</option>
                                <option value="+686" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+686') ? 'selected' : ''; ?>>Kiribati (+686)</option>
                                <option value="+965" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+965') ? 'selected' : ''; ?>>Kuwait (+965)</option>
                                <option value="+996" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+996') ? 'selected' : ''; ?>>Kyrgyzstan (+996)</option>
                                <option value="+856" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+856') ? 'selected' : ''; ?>>Laos (+856)</option>
                                <option value="+371" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+371') ? 'selected' : ''; ?>>Latvia (+371)</option>
                                <option value="+961" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+961') ? 'selected' : ''; ?>>Lebanon (+961)</option>
                                <option value="+266" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+266') ? 'selected' : ''; ?>>Lesotho (+266)</option>
                                <option value="+231" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+231') ? 'selected' : ''; ?>>Liberia (+231)</option>
                                <option value="+218" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+218') ? 'selected' : ''; ?>>Libya (+218)</option>
                                <option value="+423" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+423') ? 'selected' : ''; ?>>Liechtenstein (+423)</option>
                                <option value="+370" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+370') ? 'selected' : ''; ?>>Lithuania (+370)</option>
                                <option value="+352" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+352') ? 'selected' : ''; ?>>Luxembourg (+352)</option>
                                <option value="+261" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+261') ? 'selected' : ''; ?>>Madagascar (+261)</option>
                                <option value="+265" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+265') ? 'selected' : ''; ?>>Malawi (+265)</option>
                                <option value="+60" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+60') ? 'selected' : ''; ?>>Malaysia (+60)</option>
                                <option value="+960" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+960') ? 'selected' : ''; ?>>Maldives (+960)</option>
                                <option value="+223" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+223') ? 'selected' : ''; ?>>Mali (+223)</option>
                                <option value="+356" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+356') ? 'selected' : ''; ?>>Malta (+356)</option>
                                <option value="+692" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+692') ? 'selected' : ''; ?>>Marshall Islands (+692)</option>
                                <option value="+596" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+596') ? 'selected' : ''; ?>>Martinique (+596)</option>
                                <option value="+222" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+222') ? 'selected' : ''; ?>>Morocco (+222)</option>
                                <option value="+258" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+258') ? 'selected' : ''; ?>>Mozambique (+258)</option>
                                <option value="+95" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+95') ? 'selected' : ''; ?>>Myanmar (+95)</option>
                                <option value="+264" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+264') ? 'selected' : ''; ?>>Namibia (+264)</option>
                                <option value="+674" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+674') ? 'selected' : ''; ?>>Nauru (+674)</option>
                                <option value="+977" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+977') ? 'selected' : ''; ?>>Nepal (+977)</option>
                                <option value="+31" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+31') ? 'selected' : ''; ?>>Netherlands (+31)</option>
                                <option value="+599" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+599') ? 'selected' : ''; ?>>Netherlands Antilles (+599)</option>
                                <option value="+64" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+64') ? 'selected' : ''; ?>>New Zealand (+64)</option>
                                <option value="+505" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+505') ? 'selected' : ''; ?>>Nicaragua (+505)</option>
                                <option value="+227" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+227') ? 'selected' : ''; ?>>Niger (+227)</option>
                                <option value="+234" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+234') ? 'selected' : ''; ?>>Nigeria (+234)</option>
                                <option value="+683" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+683') ? 'selected' : ''; ?>>Niue (+683)</option>
                                <option value="+672" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+672') ? 'selected' : ''; ?>>Norfolk Island (+672)</option>
                                <option value="+850" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+850') ? 'selected' : ''; ?>>North Korea (+850)</option>
                                <option value="+1-670" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-670') ? 'selected' : ''; ?>>Northern Mariana Islands (+1-670)</option>
                                <option value="+47" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+47') ? 'selected' : ''; ?>>Norway (+47)</option>
                                <option value="+968" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+968') ? 'selected' : ''; ?>>Oman (+968)</option>
                                <option value="+92" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+92') ? 'selected' : ''; ?>>Pakistan (+92)</option>
                                <option value="+680" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+680') ? 'selected' : ''; ?>>Palau (+680)</option>
                                <option value="+507" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+507') ? 'selected' : ''; ?>>Panama (+507)</option>
                                <option value="+675" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+675') ? 'selected' : ''; ?>>Papua New Guinea (+675)</option>
                                <option value="+595" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+595') ? 'selected' : ''; ?>>Paraguay (+595)</option>
                                <option value="+51" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+51') ? 'selected' : ''; ?>>Peru (+51)</option>
                                <option value="+63" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+63') ? 'selected' : ''; ?>>Philippines (+63)</option>
                                <option value="+48" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+48') ? 'selected' : ''; ?>>Poland (+48)</option>
                                <option value="+351" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+351') ? 'selected' : ''; ?>>Portugal (+351)</option>
                                <option value="+974" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+974') ? 'selected' : ''; ?>>Qatar (+974)</option>
                                <option value="+40" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+40') ? 'selected' : ''; ?>>Romania (+40)</option>
                                <option value="+7" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+7') ? 'selected' : ''; ?>>Russia (+7)</option>
                                <option value="+250" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+250') ? 'selected' : ''; ?>>Rwanda (+250)</option>
                                <option value="+508" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+508') ? 'selected' : ''; ?>>Saint Barthélemy (+508)</option>
                                <option value="+1-869" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-869') ? 'selected' : ''; ?>>Saint Kitts and Nevis (+1-869)</option>
                                <option value="+1-758" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-758') ? 'selected' : ''; ?>>Saint Lucia (+1-758)</option>
                                <option value="+590" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+590') ? 'selected' : ''; ?>>Saint Martin (+590)</option>
                                <option value="+1-345" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-345') ? 'selected' : ''; ?>>Cayman Islands (+1-345)</option>
                                <option value="+239" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+239') ? 'selected' : ''; ?>>São Tomé and Príncipe (+239)</option>
                                <option value="+966" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+966') ? 'selected' : ''; ?>>Saudi Arabia (+966)</option>
                                <option value="+221" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+221') ? 'selected' : ''; ?>>Senegal (+221)</option>
                                <option value="+381" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+381') ? 'selected' : ''; ?>>Serbia (+381)</option>
                                <option value="+248" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+248') ? 'selected' : ''; ?>>Seychelles (+248)</option>
                                <option value="+232" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+232') ? 'selected' : ''; ?>>Sierra Leone (+232)</option>
                                <option value="+65" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+65') ? 'selected' : ''; ?>>Singapore (+65)</option>
                                <option value="+421" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+421') ? 'selected' : ''; ?>>Slovakia (+421)</option>
                                <option value="+386" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+386') ? 'selected' : ''; ?>>Slovenia (+386)</option>
                                <option value="+677" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+677') ? 'selected' : ''; ?>>Solomon Islands (+677)</option>
                                <option value="+252" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+252') ? 'selected' : ''; ?>>Somalia (+252)</option>
                                <option value="+27" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+27') ? 'selected' : ''; ?>>South Africa (+27)</option>
                                <option value="+82" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+82') ? 'selected' : ''; ?>>South Korea (+82)</option>
                                <option value="+211" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+211') ? 'selected' : ''; ?>>South Sudan (+211)</option>
                                <option value="+34" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+34') ? 'selected' : ''; ?>>Spain (+34)</option>
                                <option value="+94" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+94') ? 'selected' : ''; ?>>Sri Lanka (+94)</option>
                                <option value="+249" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+249') ? 'selected' : ''; ?>>Sudan (+249)</option>
                                <option value="+597" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+597') ? 'selected' : ''; ?>>Suriname (+597)</option>
                                <option value="+268" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+268') ? 'selected' : ''; ?>>Swaziland (+268)</option>
                                <option value="+46" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+46') ? 'selected' : ''; ?>>Sweden (+46)</option>
                                <option value="+41" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+41') ? 'selected' : ''; ?>>Switzerland (+41)</option>
                                <option value="+963" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+963') ? 'selected' : ''; ?>>Syria (+963)</option>
                                <option value="+886" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+886') ? 'selected' : ''; ?>>Taiwan (+886)</option>
                                <option value="+992" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+992') ? 'selected' : ''; ?>>Tajikistan (+992)</option>
                                <option value="+255" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+255') ? 'selected' : ''; ?>>Tanzania (+255)</option>
                                <option value="+66" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+66') ? 'selected' : ''; ?>>Thailand (+66)</option>
                                <option value="+670" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+670') ? 'selected' : ''; ?>>Timor-Leste (+670)</option>
                                <option value="+228" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+228') ? 'selected' : ''; ?>>Togo (+228)</option>
                                <option value="+676" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+676') ? 'selected' : ''; ?>>Tonga (+676)</option>
                                <option value="+1-868" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-868') ? 'selected' : ''; ?>>Trinidad and Tobago (+1-868)</option>
                                <option value="+216" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+216') ? 'selected' : ''; ?>>Tunisia (+216)</option>
                                <option value="+90" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+90') ? 'selected' : ''; ?>>Turkey (+90)</option>
                                <option value="+993" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+993') ? 'selected' : ''; ?>>Turkmenistan (+993)</option>
                                <option value="+1-649" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+1-649') ? 'selected' : ''; ?>>Turks and Caicos Islands (+1-649)</option>
                                <option value="+688" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+688') ? 'selected' : ''; ?>>Vanuatu (+688)</option>
                                <option value="+39" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+39') ? 'selected' : ''; ?>>Vatican City (+39)</option>
                                <option value="+58" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+58') ? 'selected' : ''; ?>>Venezuela (+58)</option>
                                <option value="+84" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+84') ? 'selected' : ''; ?>>Vietnam (+84)</option>
                                <option value="+681" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+681') ? 'selected' : ''; ?>>Wallis and Futuna (+681)</option>
                                <option value="+967" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+967') ? 'selected' : ''; ?>>Yemen (+967)</option>
                                <option value="+260" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+260') ? 'selected' : ''; ?>>Zambia (+260)</option>
                                <option value="+263" <?php echo (isset($row['countryCode2']) && $row['countryCode2'] == '+263') ? 'selected' : ''; ?>>Zimbabwe (+263)</option>

                              </select>
                              <input type="tel" class="form-control" name="2ndcontactNo" placeholder="Enter Contact No" value="<?php echo $row['contactNo2']; ?>">
                            </div>
                          </div>
                        </div>

                        <div class="col-md-4">
                          <div class="form-group mb-4">
                            <label class="mb-2" for="email">Email <span class="text-danger fw-bold">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="Enter Email Address" value="<?php echo $row['emailAdd']; ?>" required>
                            <span id="emailError" class="text-danger"></span> <!-- Error message for Email -->
                          </div>
                        </div>
                      </div>

                      <!--  Guest Address Information -->
                      <div class="header-container d-flex flex-row w-100 mb-3">
                        <h5 class="card-title bg-primary text-white p-3 w-100">Address Information</h5>
                      </div>

                      <div class="row">
                        <!-- Address Line 1 -->
                        <div class="col-md-6">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="addressLine">Address Line 1 <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="addressLine" class="form-control" placeholder="Enter Address Line 1" value="<?php echo $row['addressLine1']; ?>" required>
                            <small class="form-text text-muted">E.g., Street, Barangay</small> <!-- Instruction for Address Line 1 -->
                            <span id="addressLineError" class="text-danger"></span>
                          </div>
                        </div>

                        <!-- Address Line 2 -->
                        <div class="col-md-6">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="addressLine2">Address Line 2 (Optional)</label>
                            <input type="text" name="2ndaddressLine" class="form-control" placeholder="Enter Address Line 2" value="<?php echo $row['addressLine2']; ?>">
                            <small class="form-text text-muted">E.g., Subdivision, Apartment, Unit, Floor</small> <!-- Instruction for Address Line 2 -->
                          </div>
                        </div>

                        <!-- City -->
                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="city">City <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="city" class="form-control" placeholder="Enter City" value="<?php echo $row['city']; ?>" required>
                            <span id="cityError" class="text-danger"></span> <!-- Error message for City -->
                          </div>
                        </div>

                        <!-- State/Province/Region -->
                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="state">State/Province/Region <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="state" class="form-control" placeholder="Enter State/Province/Region" value="<?php echo $row['state']; ?>" required>
                            <span id="stateError" class="text-danger"></span> <!-- Error message for State -->
                          </div>
                        </div>

                        <!-- Zip/Postal Code -->
                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="zipCode">Zip/Postal Code <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="zipCode" class="form-control" placeholder="Enter Zip/Postal Code" value="<?php echo $row['zipCode']; ?>" required>
                            <span id="zipCodeError" class="text-danger"></span> <!-- Error message for Zip/Postal Code -->
                          </div>
                        </div>

                        <!-- Country -->
                        <div class="col-md-4">
                          <div class="form-group mb-3">
                            <label class="mb-2" for="country">Country <span class="text-danger fw-bold">*</span></label>
                            <input type="text" name="country" class="form-control" list="countries" placeholder="Enter Country" value="<?php echo $row['country']; ?>" required>
                            <datalist id="countries">
                              <option value="Afghanistan">Afghanistan</option>
                              <option value="Albania">Albania</option>
                              <option value="Algeria">Algeria</option>
                              <option value="Andorra">Andorra</option>
                              <option value="Angola">Angola</option>
                              <option value="Antigua">Antigua</option>
                              <option value="Barbuda">Barbuda</option>
                              <option value="Argentina">Argentina</option>
                              <option value="Armenia">Armenia</option>
                              <option value="Australia">Australia</option>
                              <option value="Austria">Austria</option>
                              <option value="Azerbaijan">Azerbaijan</option>
                              <option value="Bahamas">Bahamas</option>
                              <option value="Bahrain">Bahrain</option>
                              <option value="Bangladesh">Bangladesh</option>
                              <option value="Barbados">Barbados</option>
                              <option value="Belarus">Belarus</option>
                              <option value="Belgium">Belgium</option>
                              <option value="Belize">Belize</option>
                              <option value="Benin">Benin</option>
                              <option value="Bhutan">Bhutan</option>
                              <option value="Bolivia">Bolivia</option>
                              <option value="Bosnia">Bosnia</option>
                              <option value="Herzegovina">Herzegovina</option>
                              <option value="Botswana">Botswana</option>
                              <option value="Brazil">Brazil</option>
                              <option value="Brunei">Brunei</option>
                              <option value="Bulgaria">Bulgaria</option>
                              <option value="Burkina Faso">Burkina Faso</option>
                              <option value="Burundi">Burundi</option>
                              <option value="Cabo Verde">Cabo Verde</option>
                              <option value="Cambodia">Cambodia</option>
                              <option value="Cameroon">Cameroon</option>
                              <option value="Canada">Canada</option>
                              <option value="Central African Republic">Central African Republic</option>
                              <option value="Chad">Chad</option>
                              <option value="Chile">Chile</option>
                              <option value="China">China</option>
                              <option value="Colombia">Colombia</option>
                              <option value="Comoros">Comoros</option>
                              <option value="Congo">Congo</option>
                              <option value="Costa Rica">Costa Rica</option>
                              <option value="Croatia">Croatia</option>
                              <option value="Cuba">Cuba</option>
                              <option value="Cyprus">Cyprus</option>
                              <option value="Czech Republic">Czech Republic</option>
                              <option value="Denmark">Denmark</option>
                              <option value="Djibouti">Djibouti</option>
                              <option value="Dominica">Dominica</option>
                              <option value="Dominican Republic">Dominican Republic</option>
                              <option value="Ecuador">Ecuador</option>
                              <option value="Egypt">Egypt</option>
                              <option value="El Salvador">El Salvador</option>
                              <option value="Equatorial Guinea">Equatorial Guinea</option>
                              <option value="Eritrea">Eritrea</option>
                              <option value="Estonia">Estonia</option>
                              <option value="Eswatini">Eswatini</option>
                              <option value="Ethiopia">Ethiopia</option>
                              <option value="Fiji">Fiji</option>
                              <option value="Finland">Finland</option>
                              <option value="France">France</option>
                              <option value="Gabon">Gabon</option>
                              <option value="Gambia">Gambia</option>
                              <option value="Georgia">Georgia</option>
                              <option value="Germany">Germany</option>
                              <option value="Ghana">Ghana</option>
                              <option value="Greece">Greece</option>
                              <option value="Grenada">Grenada</option>
                              <option value="Guatemala">Guatemala</option>
                              <option value="Guinea">Guinea</option>
                              <option value="Guinea-Bissau">Guinea-Bissau</option>
                              <option value="Guyana">Guyana</option>
                              <option value="Haiti">Haiti</option>
                              <option value="Honduras">Honduras</option>
                              <option value="Hungary">Hungary</option>
                              <option value="Iceland">Iceland</option>
                              <option value="India">India</option>
                              <option value="Indonesia">Indonesia</option>
                              <option value="Iran">Iran</option>
                              <option value="Iraq">Iraq</option>
                              <option value="Ireland">Ireland</option>
                              <option value="Israel">Israel</option>
                              <option value="Italy">Italy</option>
                              <option value="Jamaica">Jamaica</option>
                              <option value="Japan">Japan</option>
                              <option value="Jordan">Jordan</option>
                              <option value="Kazakhstan">Kazakhstan</option>
                              <option value="Kenya">Kenya</option>
                              <option value="Kiribati">Kiribati</option>
                              <option value="Kuwait">Kuwait</option>
                              <option value="Kyrgyzstan">Kyrgyzstan</option>
                              <option value="Laos">Laos</option>
                              <option value="Latvia">Latvia</option>
                              <option value="Lebanon">Lebanon</option>
                              <option value="Lesotho">Lesotho</option>
                              <option value="Liberia">Liberia</option>
                              <option value="Libya">Libya</option>
                              <option value="Liechtenstein">Liechtenstein</option>
                              <option value="Lithuania">Lithuania</option>
                              <option value="Luxembourg">Luxembourg</option>
                              <option value="Madagascar">Madagascar</option>
                              <option value="Malawi">Malawi</option>
                              <option value="Malaysia">Malaysia</option>
                              <option value="Maldives">Maldives</option>
                              <option value="Mali">Mali</option>
                              <option value="Malta">Malta</option>
                              <option value="Marshall Islands">Marshall Islands</option>
                              <option value="Mauritania">Mauritania</option>
                              <option value="Mauritius">Mauritius</option>
                              <option value="Mexico">Mexico</option>
                              <option value="Micronesia">Micronesia</option>
                              <option value="Moldova">Moldova</option>
                              <option value="Monaco">Monaco</option>
                              <option value="Mongolia">Mongolia</option>
                              <option value="Montenegro">Montenegro</option>
                              <option value="Morocco">Morocco</option>
                              <option value="Mozambique">Mozambique</option>
                              <option value="Myanmar">Myanmar</option>
                              <option value="Namibia">Namibia</option>
                              <option value="Nauru">Nauru</option>
                              <option value="Nepal">Nepal</option>
                              <option value="Netherlands">Netherlands</option>
                              <option value="New Zealand">New Zealand</option>
                              <option value="Nicaragua">Nicaragua</option>
                              <option value="Niger">Niger</option>
                              <option value="Nigeria">Nigeria</option>
                              <option value="North Macedonia">North Macedonia</option>
                              <option value="Norway">Norway</option>
                              <option value="Oman">Oman</option>
                              <option value="Pakistan">Pakistan</option>
                              <option value="Palau">Palau</option>
                              <option value="Panama">Panama</option>
                              <option value="Papua New Guinea">Papua New Guinea</option>
                              <option value="Paraguay">Paraguay</option>
                              <option value="Peru">Peru</option>
                              <option value="Philippines">Philippines</option>
                              <option value="Poland">Poland</option>
                              <option value="Portugal">Portugal</option>
                              <option value="Qatar">Qatar</option>
                              <option value="Romania">Romania</option>
                              <option value="Russia">Russia</option>
                              <option value="Rwanda">Rwanda</option>
                              <option value="Saint Kitts">Saint Kitts</option>
                              <option value="Saint Nevis">Saint Nevis</option>
                              <option value="Saint Vincent">Saint Vincent</option>
                              <option value="Grenadines">Grenadines</option>
                              <option value="Sao Tome">Sao Tome</option>
                              <option value="Principe">Principe</option>
                              <option value="Saudi Arabia">Saudi Arabia</option>
                              <option value="Senegal">Senegal</option>
                              <option value="Serbia">Serbia</option>
                              <option value="Seychelles">Seychelles</option>
                              <option value="Sierra Leone">Sierra Leone</option>
                              <option value="Singapore">Singapore</option>
                              <option value="Slovakia">Slovakia</option>
                              <option value="Slovenia">Slovenia</option>
                              <option value="Solomon Islands">Solomon Islands</option>
                              <option value="Somalia">Somalia</option>
                              <option value="South Africa">South Africa</option>
                              <option value="South Korea">South Korea</option>
                              <option value="South Sudan">South Sudan</option>
                              <option value="Spain">Spain</option>
                              <option value="Sri Lanka">Sri Lanka</option>
                              <option value="Sudan">Sudan</option>
                              <option value="Suriname">Suriname</option>
                              <option value="Sweden">Sweden</option>
                              <option value="Switzerland">Switzerland</option>
                              <option value="Syria">Syria</option>
                              <option value="Tajikistan">Tajikistan</option>
                              <option value="Tanzania">Tanzania</option>
                              <option value="Thailand">Thailand</option>
                              <option value="Timor-Leste">Timor-Leste</option>
                              <option value="Togo">Togo</option>
                              <option value="Tonga">Tonga</option>
                              <option value="Trinidad">Trinidad</option>
                              <option value="Tobago">Tobago</option>
                              <option value="Tunisia">Tunisia</option>
                              <option value="Turkey">Turkey</option>
                              <option value="Turkmenistan">Turkmenistan</option>
                              <option value="Tuvalu">Tuvalu</option>
                              <option value="Uganda">Uganda</option>
                              <option value="Ukraine">Ukraine</option>
                              <option value="United Arab Emirates">United Arab Emirates</option>
                              <option value="United Kingdom">United Kingdom</option>
                              <option value="United States">United States</option>
                              <option value="Uruguay">Uruguay</option>
                              <option value="Uzbekistan">Uzbekistan</option>
                              <option value="Vanuatu">Vanuatu</option>
                              <option value="Vatican City">Vatican City</option>
                              <option value="Venezuela">Venezuela</option>
                              <option value="Vietnam">Vietnam</option>
                              <option value="Yemen">Yemen</option>
                              <option value="Zambia">Zambia</option>
                              <option value="Zimbabwe">Zimbabwe</option>
                            </datalist>
                            <span id="countryError" class="text-danger"></span> <!-- Error message for Country -->
                          </div>
                        </div>
                      </div>
                    <?php }
                  ?>   
                </div>
                <div class="card-footer d-flex justify-content-end mb-5 my-3">
                  <button type="submit" class="btn btn-primary" id="updateGuestInfo" name="updateGuestInfo">Update Guest Information</button>
                </div>

            </form>
          </div>
        </div>
      </div>
    </div>
    
  </div>

  <?php require "../Agent Section/includes/scripts.php"; ?>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>
    $(document).ready(function () 
    {
      // Validation logic for booking
      $('#updateGuestInfo').click(function (event) 
      {
        let isValid = true; // Initialize isValid flag
        let allExpPassportValid = true; // Initialize flag for expPassportSpan validation

        // Validate Primary Guest fields
        $('.guest-form').each(function (index) 
        {
          const guestFormNumber = index; // Get guest form number
          const guestFields = [
            { name: 'fName', error: 'First name is required.' },
            { name: 'lName', error: 'Last name is required.' },
            { name: 'mName', error: 'Middle name is required.' },
            { name: 'suffix', error: 'Suffix is required.', isSelect: true },
            { name: 'birthdate', error: 'Birthdate is required.' },
            { name: 'age', error: 'Age is required.' },
            { name: 'sex', error: 'Sex is required.', isSelect: true },
            { name: 'nationality', error: 'Nationality is required.' },
            { name: 'passportNo', error: 'Passport number is required.' },
            { name: 'passportExp', error: 'Passport expiration date is required.' },
            { name: 'countryCode', error: 'Country Code is required.', isSelect: true },
            { name: 'contactNo', error: 'Contact number is required.' },
            { name: 'email', error: 'Email is required.' },
            { name: 'addressLine', error: 'Address is required.' },
            { name: 'city', error: 'City is required.' },
            { name: 'state', error: 'State is required.' },
            { name: 'zipCode', error: 'Zip Code is required.' },
            { name: 'country', error: 'Country is required.' }
          ];

          // Iterate through the fields to validate
          guestFields.forEach(({ name, error, isSelect }) => 
          {
            const errorSpanId = `#${name}Error`;
            const input = isSelect
              ? $(this).find(`select[name^="${name}"]`)
              : $(this).find(`input[name^="${name}"]`);

            if (!input.val()) 
            {
              input.addClass('is-invalid'); // Add invalid class
              $(errorSpanId).text(error); // Set error message dynamically
              isValid = false; // Set valid flag to false
            }

            // Clear error when input field is focused or changed
            input.on('focus change', function () 
            {
              $(this).removeClass('is-invalid'); // Remove invalid class
              $(errorSpanId).text(''); // Clear error message
            });
          });

          // Check expPassportSpan for this form
          const expPassportSpan = $(this).find('span[id^="expPassport"]');
          if (expPassportSpan.text().trim() !== '')
           {
            allExpPassportValid = false; // Mark as invalid if any expPassportSpan is not empty
          }
        });

        // Validate Cloned Guest fields (same as above)
        // This section can remain unchanged unless specific logic for cloned fields differs

        // Check overall validity
        if (isValid && allExpPassportValid) 
        {
          console.log("Submitting");
          $('#guestForm').submit(); // Submit the form with ID #guestForm
        } 
        else if (!allExpPassportValid) 
        {
          event.preventDefault(); // Prevent default form submission
          let alertMessage = "Make Sure the passport would not expire for another six months before you depart.";
          alert(alertMessage); // Display alert with errors
        }
        else 
        {
          event.preventDefault(); // Prevent default form submission
          let alertMessage = "Some required fields are empty or not valid.";
          alert(alertMessage); // Display alert with errors
        }
      });
    });
  </script>
  
  <!-- Update birthdate and passport exp -->
  <script>
    $(document).ready(function () 
    {
      // Event listener for birthdate field
      $(document).on('change', 'input[name^="birthdate"]', function () 
      {
        const birthdate = $(this).val();

        // Make sure the birthdate is in a valid format (YYYY-MM-DD)
        if (isValidDate(birthdate)) 
        {
          const age = calculateAge(birthdate); // Calculate age

          // Update the age field and handle infant text
          const parentCard = $(this).closest('.card-body');
          parentCard.find('input[name="age"]').val(age > 0 ? age : 0);

          const infantSpan = $('#infant');
          if (age === 0) 
          {
            infantSpan.text('Infant'); // Display "Infant" for age 0
          } 
          else 
          {
            infantSpan.text(''); // Clear if not an infant
          }
        } 
        // else 
        // {
        //   // Clear invalid fields
        //   $(this).closest('.card-body').find('input[name^="age"]').val('');
        //   $(this).closest('.card-body').find('span[id^="infant"]').text('');
        // }
      });

      $(document).on('change', 'input[name^="passportExp"]', function () 
      {
        const passportExp = $(this).val(); // Get the passport expiration date from the input field
        const flightDate = '<?php echo $flightdate; ?>'; // PHP variable for the flight date
        const expPassportSpan = $('#expPassport'); // Target the span element

        // Parse dates
        const passportExpiryDate = new Date(passportExp); // Convert the expiration date to a JavaScript Date object
        const flightDateObj = new Date(flightDate); // Convert the flight date to a JavaScript Date object

        // Validate the input date format and logic
        if (isNaN(passportExpiryDate.getTime())) 
        {
          expPassportSpan.text("Invalid passport expiration date format. Please use YYYY-MM-DD.");
          return;
        }

        if (isNaN(flightDateObj.getTime())) 
        {
          expPassportSpan.text("Invalid flight date provided.");
          return;
        }

        // Calculate 6 months before the passport expiration date
        const sixMonthsBeforeExpiry = new Date(passportExpiryDate);
        sixMonthsBeforeExpiry.setMonth(sixMonthsBeforeExpiry.getMonth() - 6);

        // Check if the flight date satisfies the 6-month rule
        if (flightDateObj < sixMonthsBeforeExpiry) 
        {
          expPassportSpan.text("");
        } 
        else 
        {
          expPassportSpan.text("Your passport does not meet the 6-month validity rule for this flight date.");
        }
      });

      // Function to calculate age from birthdate
      function calculateAge(birthdate) 
      {
        const birthDateObj = new Date(birthdate); // Convert birthdate string into Date object
        const today = new Date();
        let age = today.getFullYear() - birthDateObj.getFullYear();

        // Adjust if the birthday hasn't occurred yet this year
        const monthDiff = today.getMonth() - birthDateObj.getMonth();
        const dayDiff = today.getDate() - birthDateObj.getDate();

        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) 
        {
          age--;
        }

        return age; // Do not apply the < 1 check; this ensures 0 is returned if the age is exactly 0
      }

      // Function to check if the date is in a valid format (YYYY-MM-DD)
      function isValidDate(dateString) 
      {
        // Check if the date is in the format YYYY-MM-DD
        const regex = /^\d{4}-\d{2}-\d{2}$/;
        return regex.test(dateString);
      }
    });
  </script>
  
</body>
</html>