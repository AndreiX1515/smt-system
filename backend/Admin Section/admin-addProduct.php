<?php
session_start();
require "../conn.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>  - </title>

  <?php include "../Admin Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Admin Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Admin Section/assets/css/admin-addProduct.css?v=<?php echo time(); ?>">
</head>

<body>
  <div class="body-container">
    <?php include "../Admin Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <!-- Progress Header -->
      <div class="progress-header">
        <div class="progress-step active" data-step="1">
          <div class="step-number">STEP 1</div>
          <div class="step-title"> </div>
        </div>
        <div class="progress-divider"></div>
        <div class="progress-step" data-step="2">
          <div class="step-number">STEP 2</div>
          <div class="step-title"> </div>
        </div>
      </div>

      <!-- Step 1: Product Registration -->
      <div class="step-content" id="step1" style="display: block;">
        <div class="step-container">

          <!-- Case Selection Tabs -->
          <div class="case-tabs">
            <button class="case-tab active" data-case="case1">
              <div class="case-number">Case 1</div>
              <div class="case-title"> -  </div>
            </button>
            <button class="case-tab" data-case="case2-1">
              <div class="case-number">Case 2</div>
              <div class="case-title">    </div>
            </button>
            <button class="case-tab" data-case="case2-2">
              <div class="case-number">Case 2</div>
              <div class="case-title">    </div>
            </button>
            <button class="case-tab" data-case="case2-3">
              <div class="case-number">Case 2</div>
              <div class="case-title">     </div>
            </button>
          </div>

          <!-- Case 1 Form -->
          <div class="case-content active" id="case1">
            <form id="productForm">

              <!--   -->
              <div class="form-section">
                <h3 class="section-title"> </h3>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label required"></label>
                    <input type="text" class="form-input" name="packageName" required placeholder=" ">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group half">
                    <label class="form-label required"></label>
                    <select class="form-select" name="packageCategory" required>
                      <option value=""></option>
                      <option value="season"></option>
                      <option value="region"></option>
                      <option value="theme"></option>
                    </select>
                  </div>
                  <div class="form-group half">
                    <label class="form-label required"> </label>
                    <select class="form-select" name="packageType" required>
                      <option value="standard"></option>
                      <option value="premium"></option>
                      <option value="luxury"></option>
                    </select>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group half">
                    <label class="form-label required"> (₱)</label>
                    <input type="number" class="form-input" name="packagePrice" step="0.01" required placeholder="0.00">
                  </div>
                  <div class="form-group half">
                    <label class="form-label required"></label>
                    <input type="text" class="form-input" name="duration" required placeholder=": 3 4">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"> </label>
                    <textarea class="form-textarea" name="packageDescription" rows="4" placeholder="    "></textarea>
                  </div>
                </div>
              </div>

              <!--   -->
              <div class="form-section">
                <h3 class="section-title"> </h3>

                <div class="form-row">
                  <div class="form-group half">
                    <label class="form-label">  ()</label>
                    <input type="number" class="form-input" name="duration_days" value="3" min="1">
                  </div>
                  <div class="form-group half">
                    <label class="form-label"></label>
                    <select class="form-select" name="difficulty">
                      <option value="easy"></option>
                      <option value="moderate"></option>
                      <option value="challenging"></option>
                    </select>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group half">
                    <label class="form-label"> </label>
                    <input type="text" class="form-input" name="meeting_location" placeholder=":  3">
                  </div>
                  <div class="form-group half">
                    <label class="form-label"> </label>
                    <input type="time" class="form-input" name="meeting_time" value="09:00">
                  </div>
                </div>
              </div>

              <!--   -->
              <div class="form-section">
                <h3 class="section-title"> </h3>

                <div class="form-row">
                  <div class="form-group half">
                    <label class="form-label"> </label>
                    <input type="number" class="form-input" name="minParticipants" value="1" min="1">
                  </div>
                  <div class="form-group half">
                    <label class="form-label"> </label>
                    <input type="number" class="form-input" name="maxParticipants" value="50" min="1">
                  </div>
                </div>
              </div>

              <!-- /  -->
              <div class="form-section">
                <h3 class="section-title"> </h3>
                <div id="includesContainer">
                  <div class="dynamic-item">
                    <input type="text" class="form-input" placeholder="  ">
                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <button type="button" class="btn-add" onclick="addIncludeItem()">
                  <i class="fas fa-plus"></i>  
                </button>
              </div>

              <div class="form-section">
                <h3 class="section-title"> </h3>
                <div id="excludesContainer">
                  <div class="dynamic-item">
                    <input type="text" class="form-input" placeholder="  ">
                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <button type="button" class="btn-add" onclick="addExcludeItem()">
                  <i class="fas fa-plus"></i>  
                </button>
              </div>

              <!--  -->
              <div class="form-section">
                <h3 class="section-title"></h3>
                <div id="highlightsContainer">
                  <div class="dynamic-item">
                    <input type="text" class="form-input" placeholder=" ">
                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <button type="button" class="btn-add" onclick="addHighlightItem()">
                  <i class="fas fa-plus"></i>  
                </button>
              </div>

              <!--  -->
              <div class="form-section">
                <h3 class="section-title"> </h3>
                <div id="imagesContainer">
                  <div class="dynamic-item">
                    <input type="text" class="form-input" placeholder=" URL ">
                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <button type="button" class="btn-add" onclick="addImageItem()">
                  <i class="fas fa-plus"></i>  
                </button>
              </div>

              <!-- Action Buttons -->
              <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-manageProducts.php'">
                  
                </button>
                <button type="button" class="btn-primary" onclick="goToStep2()">
                  :  <i class="fas fa-arrow-right"></i>
                </button>
              </div>

            </form>
          </div>

          <!-- Other cases (simplified for now) -->
          <div class="case-content" id="case2-1">
            <p class="info-message">Case 2-1    .</p>
          </div>
          <div class="case-content" id="case2-2">
            <p class="info-message">Case 2-2    .</p>
          </div>
          <div class="case-content" id="case2-3">
            <p class="info-message">Case 2-3    .</p>
          </div>

        </div>
      </div>

      <!-- Step 2: Preview -->
      <div class="step-content" id="step2" style="display: none;">
        <div class="preview-container">
          <h2 class="preview-title"> </h2>

          <div class="preview-section">
            <h3> </h3>
            <div class="preview-grid">
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-packageName">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-packageCategory">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-packageType">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-packagePrice">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-duration">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label">:</span>
                <span class="preview-value" id="preview-difficulty">-</span>
              </div>
            </div>

            <div class="preview-item full">
              <span class="preview-label">:</span>
              <p class="preview-description" id="preview-packageDescription">-</p>
            </div>
          </div>

          <div class="preview-section">
            <h3> </h3>
            <div class="preview-grid">
              <div class="preview-item">
                <span class="preview-label"> :</span>
                <span class="preview-value" id="preview-meeting_location">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label"> :</span>
                <span class="preview-value" id="preview-meeting_time">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label"> :</span>
                <span class="preview-value" id="preview-minParticipants">-</span>
              </div>
              <div class="preview-item">
                <span class="preview-label"> :</span>
                <span class="preview-value" id="preview-maxParticipants">-</span>
              </div>
            </div>
          </div>

          <div class="preview-section">
            <h3> </h3>
            <ul class="preview-list" id="preview-includes"></ul>
          </div>

          <div class="preview-section">
            <h3> </h3>
            <ul class="preview-list" id="preview-excludes"></ul>
          </div>

          <div class="preview-section">
            <h3></h3>
            <ul class="preview-list" id="preview-highlights"></ul>
          </div>

          <div class="preview-section">
            <h3></h3>
            <div class="preview-images" id="preview-images"></div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="goToStep1()">
              <i class="fas fa-arrow-left"></i> 
            </button>
            <button type="button" class="btn-primary" onclick="submitProduct()">
                
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>

  <?php require "../Agent Section/includes/scripts.php"; ?>
  <script src="../Admin Section/assets/js/admin-addProduct.js?v=<?php echo time(); ?>"></script>

</body>
</html>
