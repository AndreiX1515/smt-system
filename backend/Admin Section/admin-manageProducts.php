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
  <title> - </title>

  <?php include "../Admin Section/includes/head.php"; ?>

  <link rel="stylesheet" href="../Admin Section/assets/css/admin-transaction.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Admin Section/assets/css/admin-manageProducts.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../Admin Section/assets/css/navbar-sidebar.css?v=<?php echo time(); ?>">
</head>

<body>
  <div class="body-container">
    <?php include "../Admin Section/includes/sidebar.php"; ?>

    <div class="main-content-container">
      <div class="navbar">
        <h5 class="title-page"></h5>
      </div>

      <div class="main-content">
        <div class="table-wrapper">
          <div class="table-header">

            <div class="search-wrapper">
              <div class="search-input-wrapper">
                <input type="text" id="search" placeholder=",  ...">
              </div>
            </div>

            <div class="second-header-wrapper">
              <div class="date-range-wrapper sorting-wrapper">
                <div class="select-wrapper">
                  <select id="categoryFilter">
                    <option value="All"> </option>
                    <option value="season"></option>
                    <option value="region"></option>
                    <option value="theme"></option>
                  </select>
                </div>
              </div>

              <div class="date-range-wrapper sorting-wrapper">
                <div class="select-wrapper">
                  <select id="statusFilter">
                    <option value="All"> </option>
                    <option value="1"></option>
                    <option value="0"></option>
                  </select>
                </div>
              </div>

              <div class="vertical-separator"></div>

              <div class="buttons-wrapper">
                <button id="clearFilters" class="btn btn-secondary">
                   
                </button>
              </div>

              <div class="buttons-wrapper">
                <button id="addProductBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                  <i class="fas fa-plus"></i>  
                </button>
              </div>

            </div>

          </div>

          <div class="table-container">
            <table id="product-table" class="product-table">
              <thead>
                <tr>
                  <th> ID</th>
                  <th> </th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="productTableBody">
                <?php
                $sql = "SELECT
                  p.packageId,
                  p.packageName,
                  p.packageCategory,
                  p.packagePrice,
                  p.packageDuration,
                  p.packageType,
                  p.isActive,
                  p.packageImage,
                  p.createdAt,
                  p.updatedAt
                FROM packages p
                ORDER BY p.createdAt DESC";

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $status = $row['isActive'] ? '' : '';
                    $statusClass = $row['isActive'] ? 'status-active' : 'status-inactive';

                    $imageUrl = '';
                    $hasImage = false;

                    if (!empty($row['packageImage'])) {
                        $rawImagePath = trim($row['packageImage']);

                        if (filter_var($rawImagePath, FILTER_VALIDATE_URL)) {
                            $imageUrl = $rawImagePath;
                            $hasImage = true;
                        } else {
                            $normalizedPath = ltrim($rawImagePath, '/');
                            $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                            
                            //     
                            if (strpos($normalizedPath, 'uploads/products/') === 0) {
                                $normalizedPath = str_replace('uploads/products/', '', $normalizedPath);
                            }
                            
                            $possibleAbsolute = $documentRoot . '/' . $normalizedPath;
                            $uploadsDir = realpath(__DIR__ . '/../../uploads/products');
                            
                            // 1.    
                            if (file_exists($possibleAbsolute)) {
                                $relativePath = str_replace($documentRoot, '', realpath($possibleAbsolute));
                                $imageUrl = $relativePath ?: '';
                                $hasImage = !empty($imageUrl);
                            } 
                            // 2. uploads/products   
                            else if ($uploadsDir && file_exists($uploadsDir . '/' . $normalizedPath)) {
                                $relativePath = str_replace($documentRoot, '', $uploadsDir . '/' . $normalizedPath);
                                $imageUrl = $relativePath ?: '';
                                $hasImage = !empty($imageUrl);
                            } 
                            // 3.    uploads/products 
                            else if ($uploadsDir && file_exists($uploadsDir . '/' . basename($normalizedPath))) {
                                $imageUrl = '/uploads/products/' . basename($normalizedPath);
                                $hasImage = true;
                            }
                            // 4.    (   )
                            else {
                                // uploads/products  
                                $imageUrl = '/uploads/products/' . $normalizedPath;
                                $hasImage = false; //       false
                            }
                        }
                    }

                    $imageCell = $hasImage
                        ? "<img src='{$imageUrl}' alt='{$row['packageName']}' class='product-thumb' onerror=\"this.onerror=null; this.parentElement.innerHTML='<div class=\\'product-thumb no-image\\'> </div>';\">"
                        : "<div class='product-thumb no-image'> </div>";
                    $packageType = '';
                    switch($row['packageType']) {
                      case 'standard': $packageType = ''; break;
                      case 'premium': $packageType = ''; break;
                      case 'luxury': $packageType = ''; break;
                      default: $packageType = $row['packageType'];
                    }
                    $category = '';
                    switch($row['packageCategory']) {
                      case 'season': $category = ''; break;
                      case 'region': $category = ''; break;
                      case 'theme': $category = ''; break;
                      default: $category = $row['packageCategory'];
                    }

                    echo "<tr data-package-id='{$row['packageId']}' data-category='{$row['packageCategory']}' data-status='{$row['isActive']}'>
                      <td>{$row['packageId']}</td>
                      <td>{$imageCell}</td>
                      <td class='package-name'>{$row['packageName']}</td>
                      <td>{$category}</td>
                      <td>₱" . number_format($row['packagePrice'], 2) . "</td>
                      <td>{$row['packageDuration']}</td>
                      <td>{$packageType}</td>
                      <td><span class='status-badge {$statusClass}'>{$status}</span></td>
                      <td>" . date('Y-m-d', strtotime($row['createdAt'])) . "</td>
                      <td>" . ($row['updatedAt'] ? date('Y-m-d', strtotime($row['updatedAt'])) : '-') . "</td>
                      <td>
                        <div class='action-buttons'>
                          <button class='btn-action btn-edit' onclick='editProduct({$row['packageId']})' title=''>
                            <i class='fas fa-edit'></i>
                          </button>
                          <button class='btn-action btn-delete' onclick='deleteProduct({$row['packageId']})' title=''>
                            <i class='fas fa-trash'></i>
                          </button>
                          <button class='btn-action btn-view' onclick='viewProduct({$row['packageId']})' title=''>
                            <i class='fas fa-eye'></i>
                          </button>
                        </div>
                      </td>
                    </tr>";
                  }
                } else {
                  echo "<tr><td colspan='11' class='text-center'>  .</td></tr>";
                }
                ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

  </div>

  <!--    -->
  <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addProductModalLabel">  </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="addProductForm">
            <div class="mb-3">
              <label for="packageName" class="form-label"></label>
              <input type="text" class="form-control" id="packageName" name="packageName" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="packageCategory" class="form-label"></label>
                <select class="form-control" id="packageCategory" name="packageCategory" required>
                  <option value=""></option>
                  <option value="season"></option>
                  <option value="region"></option>
                  <option value="theme"></option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="packageType" class="form-label"> </label>
                <select class="form-control" id="packageType" name="packageType" required>
                  <option value="standard"></option>
                  <option value="premium"></option>
                  <option value="luxury"></option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="packagePrice" class="form-label"> (₱)</label>
                <input type="number" class="form-control" id="packagePrice" name="packagePrice" step="0.01" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="packageDuration" class="form-label"></label>
                <input type="text" class="form-control" id="packageDuration" name="packageDuration" placeholder=": 3 4" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="minParticipants" class="form-label"> </label>
                <input type="number" class="form-control" id="minParticipants" name="minParticipants" value="1">
              </div>
              <div class="col-md-6 mb-3">
                <label for="maxParticipants" class="form-label"> </label>
                <input type="number" class="form-control" id="maxParticipants" name="maxParticipants" value="50">
              </div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label"></label>
              <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
              <label for="packageImage" class="form-label">  URL</label>
              <input type="text" class="form-control" id="packageImage" name="packageImage">
            </div>

            <div class="mb-3">
              <label for="difficulty" class="form-label"></label>
              <select class="form-control" id="difficulty" name="difficulty">
                <option value="easy"></option>
                <option value="moderate"></option>
                <option value="challenging"></option>
              </select>
            </div>

            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="isActive" name="isActive" checked>
              <label class="form-check-label" for="isActive"> </label>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"></button>
          <button type="button" class="btn btn-primary" onclick="saveProduct()"></button>
        </div>
      </div>
    </div>
  </div>

  <!--    -->
  <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editProductModalLabel"> </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editProductForm">
            <input type="hidden" id="editPackageId" name="packageId">
            <div class="mb-3">
              <label for="editPackageName" class="form-label"></label>
              <input type="text" class="form-control" id="editPackageName" name="packageName" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="editPackageCategory" class="form-label"></label>
                <select class="form-control" id="editPackageCategory" name="packageCategory" required>
                  <option value="season"></option>
                  <option value="region"></option>
                  <option value="theme"></option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="editPackageType" class="form-label"> </label>
                <select class="form-control" id="editPackageType" name="packageType" required>
                  <option value="standard"></option>
                  <option value="premium"></option>
                  <option value="luxury"></option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="editPackagePrice" class="form-label"> (₱)</label>
                <input type="number" class="form-control" id="editPackagePrice" name="packagePrice" step="0.01" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="editPackageDuration" class="form-label"></label>
                <input type="text" class="form-control" id="editPackageDuration" name="packageDuration" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="editMinParticipants" class="form-label"> </label>
                <input type="number" class="form-control" id="editMinParticipants" name="minParticipants">
              </div>
              <div class="col-md-6 mb-3">
                <label for="editMaxParticipants" class="form-label"> </label>
                <input type="number" class="form-control" id="editMaxParticipants" name="maxParticipants">
              </div>
            </div>

            <div class="mb-3">
              <label for="editDescription" class="form-label"></label>
              <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
              <label for="editPackageImage" class="form-label">  URL</label>
              <input type="text" class="form-control" id="editPackageImage" name="packageImage">
            </div>

            <div class="mb-3">
              <label for="editDifficulty" class="form-label"></label>
              <select class="form-control" id="editDifficulty" name="difficulty">
                <option value="easy"></option>
                <option value="moderate"></option>
                <option value="challenging"></option>
              </select>
            </div>

            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="editIsActive" name="isActive">
              <label class="form-check-label" for="editIsActive"> </label>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"></button>
          <button type="button" class="btn btn-primary" onclick="updateProduct()"></button>
        </div>
      </div>
    </div>
  </div>

  <?php require "../Agent Section/includes/scripts.php"; ?>

  <script src="../Admin Section/assets/js/admin-manageProducts.js?v=<?php echo time(); ?>"></script>

  <script>
  function toggleSubMenu(submenuId) {
      const submenu = document.getElementById(submenuId);
      const sectionTitle = submenu.previousElementSibling;
      const chevron = sectionTitle.querySelector('.chevron-icon');

      const isOpen = submenu.classList.contains('open');

      if (isOpen) {
          submenu.classList.remove('open');
          chevron.style.transform = 'rotate(0deg)';
      } else {
          const allSubmenus = document.querySelectorAll('.submenu');
          const allChevrons = document.querySelectorAll('.chevron-icon');

          allSubmenus.forEach(sub => {
              sub.classList.remove('open');
          });

          allChevrons.forEach(chev => {
              chev.style.transform = 'rotate(0deg)';
          });

          submenu.classList.add('open');
          chevron.style.transform = 'rotate(180deg)';
      }
  }
  </script>

</body>
</html>
