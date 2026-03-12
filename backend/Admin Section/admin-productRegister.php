<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>  - Smart Travel</title>
    <link rel="stylesheet" href="assets/css/admin-productRegister.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"> </h1>
                <div class="header-actions">
                    <button type="button" class="btn-temp-save" id="saveDraftBtn"></button>
                    <button type="button" class="btn-register" id="saveProductBtn"></button>
                </div>
            </div>

            <div class="content-layout">
                <!-- Left: Form -->
                <div class="form-container">
                    <form id="productForm">

                        <!--   -->
                        <section class="section-block">
                            <h2 class="section-heading"> </h2>

                            <div class="form-field">
                                <label class="field-label"> *</label>
                                <input type="text" name="packageName" class="field-input" placeholder="" required>
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <div class="radio-group-inline">
                                    <label class="radio-option">
                                        <input type="radio" name="salesType" value="B2B" checked>
                                        <span>B2B</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="salesType" value="B2C">
                                        <span>B2C</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-row-2">
                                <div class="form-field">
                                    <label class="field-label"> *</label>
                                    <select name="packageCategory" class="field-select" required>
                                        <option value=""></option>
                                        <option value="season"></option>
                                        <option value="region"></option>
                                        <option value="theme"></option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label class="field-label"> *</label>
                                    <select name="subCategory" class="field-select" required>
                                        <option value=""></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <select name="packageType" class="field-select" required>
                                    <option value=""></option>
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium</option>
                                    <option value="luxury">Luxury</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <div class="image-upload-area large" id="thumbnailArea">
                                    <div class="upload-content">
                                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 28V12M12 20l8-8 8 8"/>
                                        </svg>
                                        <p class="upload-text"> </p>
                                        <span class="upload-hint">  100  .</span>
                                    </div>
                                    <input type="file" id="thumbnailInput" accept="image/*" hidden>
                                    <img id="thumbnailPreview" class="preview-img" style="display:none;">
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="field-label"> </label>
                                <div class="multi-upload-grid" id="productImagesGrid">
                                    <div class="image-upload-area small add-btn" id="addImageBtn">
                                        <div class="upload-content">
                                            <span class="plus-icon">+</span>
                                            <p class="upload-text"></p>
                                        </div>
                                    </div>
                                </div>
                                <input type="file" id="productImagesInput" accept="image/*" multiple hidden>
                                <p class="field-note">* 2   ,     . 1   .</p>
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <div class="image-upload-area large" id="detailImageArea">
                                    <div class="upload-content">
                                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 28V12M12 20l8-8 8 8"/>
                                        </svg>
                                        <p class="upload-text"> </p>
                                    </div>
                                    <input type="file" id="detailImageInput" accept="image/*" multiple hidden>
                                </div>
                            </div>
                        </section>

                        <!--   -->
                        <section class="section-block">
                            <h2 class="section-heading"> </h2>

                            <div class="subsection-title">  </div>

                            <div class="form-row-3">
                                <div class="form-field">
                                    <label class="field-label">  *</label>
                                    <div class="input-with-suffix">
                                        <input type="number" name="adultPrice" class="field-input" required>
                                        <span class="suffix"></span>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label class="field-label"> </label>
                                    <div class="input-with-suffix">
                                        <input type="number" name="childPrice" class="field-input">
                                        <span class="suffix"></span>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label class="field-label"> </label>
                                    <div class="input-with-suffix">
                                        <input type="number" name="infantPrice" class="field-input">
                                        <span class="suffix"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="input-with-suffix">
                                    <input type="number" name="singleSurcharge" class="field-input">
                                    <span class="suffix"></span>
                                </div>
                            </div>

                            <div class="subsection-title"> </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <div class="date-range-field">
                                    <input type="date" name="operationStartDate" class="field-input" required>
                                    <span class="range-sep">~</span>
                                    <input type="date" name="operationEndDate" class="field-input" required>
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <div class="day-checkbox-group">
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="1"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="2"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="3"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="4"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="5"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="6"><span></span></label>
                                    <label class="day-checkbox"><input type="checkbox" name="operationDays[]" value="0"><span></span></label>
                                </div>
                            </div>

                            <div class="form-row-2">
                                <div class="form-field">
                                    <label class="field-label"> </label>
                                    <div class="input-with-suffix">
                                        <input type="number" name="minParticipants" class="field-input" value="1">
                                        <span class="suffix"></span>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label class="field-label"> </label>
                                    <div class="input-with-suffix">
                                        <input type="number" name="maxParticipants" class="field-input" value="50">
                                        <span class="suffix"></span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!--  -->
                        <section class="section-block">
                            <h2 class="section-heading"></h2>

                            <div class="form-field">
                                <label class="field-label">1Day</label>
                                <textarea name="schedule_day_1" class="field-textarea" rows="6" placeholder="1  ..."></textarea>
                            </div>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <textarea name="fullSchedule" class="field-textarea" rows="6" placeholder="  ..."></textarea>
                            </div>

                            <div class="form-field">
                                <label class="field-label"> </label>
                                <input type="text" name="meetingLocation" class="field-input" placeholder=" ">
                            </div>

                            <div class="form-field">
                                <label class="field-label">  *</label>
                                <input type="time" name="meetingTime" class="field-input">
                            </div>

                            <div class="form-field">
                                <label class="field-label"> </label>
                                <div class="price-table-wrapper">
                                    <table class="price-table">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td><input type="number" class="table-input"></td>
                                                <td><input type="number" class="table-input"></td>
                                                <td><input type="number" class="table-input"></td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td><input type="number" class="table-input"></td>
                                                <td><input type="number" class="table-input"></td>
                                                <td><input type="number" class="table-input"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="form-field">
                                <label class="field-label"> </label>
                                <div class="schedule-editor">
                                    <div class="editor-toolbar-mini">
                                        <button type="button" class="toolbar-mini-btn">B</button>
                                        <button type="button" class="toolbar-mini-btn">I</button>
                                        <button type="button" class="toolbar-mini-btn">U</button>
                                    </div>
                                    <div class="editor-area" contenteditable="true" data-placeholder="  ..."></div>
                                </div>
                            </div>
                        </section>

                        <!--   -->
                        <section class="section-block">
                            <h2 class="section-heading"> </h2>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="dynamic-input-list" id="includesContainer">
                                    <div class="dynamic-input-item">
                                        <input type="text" name="includes[]" class="field-input" placeholder=" ">
                                        <button type="button" class="btn-remove-item">-</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-item" data-target="includesContainer">+   </button>
                            </div>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="dynamic-input-list" id="excludesContainer">
                                    <div class="dynamic-input-item">
                                        <input type="text" name="excludes[]" class="field-input" placeholder=" ">
                                        <button type="button" class="btn-remove-item">-</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-item" data-target="excludesContainer">+   </button>
                            </div>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <textarea name="requiredItems" class="field-textarea" rows="6" placeholder=" ..."></textarea>
                            </div>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="schedule-editor">
                                    <div class="editor-toolbar-mini">
                                        <button type="button" class="toolbar-mini-btn">B</button>
                                        <button type="button" class="toolbar-mini-btn">I</button>
                                        <button type="button" class="toolbar-mini-btn">U</button>
                                    </div>
                                    <div class="editor-area" contenteditable="true" data-placeholder=" ..."></div>
                                </div>
                            </div>
                        </section>

                        <!--  -->
                        <section class="section-block">
                            <h2 class="section-heading"></h2>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="schedule-editor">
                                    <div class="editor-toolbar-mini">
                                        <button type="button" class="toolbar-mini-btn">B</button>
                                        <button type="button" class="toolbar-mini-btn">I</button>
                                        <button type="button" class="toolbar-mini-btn">U</button>
                                    </div>
                                    <div class="editor-area" contenteditable="true" data-placeholder="  ..."></div>
                                </div>
                            </div>
                        </section>

                        <!-- /  -->
                        <section class="section-block">
                            <h2 class="section-heading">/ </h2>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="schedule-editor">
                                    <div class="editor-toolbar-mini">
                                        <button type="button" class="toolbar-mini-btn">B</button>
                                        <button type="button" class="toolbar-mini-btn">I</button>
                                        <button type="button" class="toolbar-mini-btn">U</button>
                                    </div>
                                    <div class="editor-area" contenteditable="true" data-placeholder="/  ..."></div>
                                </div>
                            </div>
                        </section>

                        <!--    -->
                        <section class="section-block">
                            <h2 class="section-heading">  </h2>

                            <div class="form-field">
                                <label class="field-label"></label>
                                <div class="schedule-editor">
                                    <div class="editor-toolbar-mini">
                                        <button type="button" class="toolbar-mini-btn">B</button>
                                        <button type="button" class="toolbar-mini-btn">I</button>
                                        <button type="button" class="toolbar-mini-btn">U</button>
                                    </div>
                                    <div class="editor-area" contenteditable="true" data-placeholder="   ..."></div>
                                </div>
                            </div>
                        </section>

                    </form>
                </div>

                <!-- Right: Summary Info -->
                <aside class="summary-panel">
                    <div class="summary-sticky">
                        <h3 class="summary-title"> </h3>

                        <div class="summary-section">
                            <div class="summary-label"> </div>
                            <div class="summary-value" id="summaryProductName">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label"></div>
                            <div class="summary-value" id="summaryCategory">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label"></div>
                            <div class="summary-value" id="summaryType">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label"></div>
                            <div class="summary-value" id="summaryPrice">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label"></div>
                            <div class="summary-value" id="summarySchedule">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label">/ </div>
                            <div class="summary-value" id="summaryParticipants">-</div>
                        </div>

                        <div class="summary-section">
                            <div class="summary-label">  </div>
                            <div class="summary-value" id="summaryStatus">-</div>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script src="assets/js/admin-productRegister.js"></script>
</body>
</html>
