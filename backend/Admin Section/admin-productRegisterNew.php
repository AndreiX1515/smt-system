<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>  - Smart Travel</title>
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✈️</text></svg>">
    <link rel="stylesheet" href="assets/css/admin-productRegisterNew.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content-area">
            <div class="form-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title"> </h1>
                    <div class="header-actions">
                        <button type="button" class="btn-outline" onclick="fillTestData()" style="background-color: #ff9800; color: white; border-color: #ff9800;"> </button>
                        <button type="button" class="btn-outline" onclick="saveDraft()"></button>
                        <button type="button" class="btn-primary" onclick="submitForm()"></button>
                    </div>
                </div>

                <form id="productRegistrationForm">

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <input type="text" name="productName" class="text-input" placeholder="" required>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <select name="salesTarget" class="select-input" required>
                            <option value=""></option>
                            <option value="b2b">B2B</option>
                            <option value="b2c">B2C</option>
                        </select>
                    </div>

                    <!--  /  -->
                    <div class="form-row-split">
                        <div class="form-field-group">
                            <label class="field-label required"></label>
                            <select name="mainCategory" class="select-input" id="mainCategory" required>
                                <option value=""> </option>
                                <option value="season"></option>
                                <option value="region"></option>
                                <option value="theme"></option>
                            </select>
                        </div>
                        <div class="form-field-group">
                            <label class="field-label required"></label>
                            <select name="subCategory" class="select-input" id="subCategory" required>
                                <option value="">  </option>
                            </select>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select font-select">
                                    <option></option>
                                    <option></option>
                                    <option></option>
                                </select>
                                <select class="toolbar-select size-select">
                                    <option>Arial</option>
                                    <option> </option>
                                    <option>Noto Sans</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                    <option>12</option>
                                    <option>14</option>
                                    <option>16</option>
                                    <option>18</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold" title=""><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic" title=""><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline" title=""><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor" title=""><span style="color:#666">A</span></button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft" title=" ">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter" title=" ">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight" title=" ">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink" title="">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="image-upload-box single" id="thumbnailUpload">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="upload-trigger-btn">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                            </div>
                            <input type="file" id="thumbnailInput" accept="image/*" hidden>
                            <div class="image-preview" id="thumbnailPreview" style="display:none;"></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label"> </label>
                        <div class="image-upload-grid" id="productImagesGrid">
                            <div class="image-upload-box grid-item">
                                <button type="button" class="upload-trigger-btn small" data-upload-index="0">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                                <div class="image-preview" style="display:none;"></div>
                            </div>
                            <div class="image-upload-box grid-item">
                                <button type="button" class="upload-trigger-btn small" data-upload-index="1">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                                <div class="image-preview" style="display:none;"></div>
                            </div>
                            <div class="image-upload-box grid-item">
                                <button type="button" class="upload-trigger-btn small" data-upload-index="2">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                                <div class="image-preview" style="display:none;"></div>
                            </div>
                            <div class="image-upload-box grid-item">
                                <button type="button" class="upload-trigger-btn small" data-upload-index="3">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                                <div class="image-preview" style="display:none;"></div>
                            </div>
                            <div class="image-upload-box grid-item">
                                <button type="button" class="upload-trigger-btn small" data-upload-index="4">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                                <div class="image-preview" style="display:none;"></div>
                            </div>
                        </div>
                        <input type="file" id="productImagesInput" accept="image/*" hidden>
                    </div>

                    <!--    -->
                    <div class="form-field-group">
                        <label class="field-label required">  </label>
                        <div class="image-upload-box single" id="detailImageUpload">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="upload-trigger-btn">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                            </div>
                            <input type="file" id="detailImageInput" accept="image/*" hidden>
                            <div class="image-preview" id="detailImagePreview" style="display:none;"></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"> </h2>

                    <h3 class="subsection-title"></h3>

                    <!--    -->
                    <div class="form-field-group">
                        <label class="field-label"></label>
                        <input type="text" name="departureFlightNumber" class="text-input" placeholder=": KE123">
                    </div>

                    <!--   /   -->
                    <div class="form-row-split">
                        <div class="form-field-group">
                            <label class="field-label"> </label>
                            <div class="time-picker-group">
                                <select name="departureFlightDepartureHour" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<24; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-separator">:</span>
                                <select name="departureFlightDepartureMinute" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"> </label>
                            <div class="time-picker-group">
                                <select name="departureFlightArrivalHour" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<24; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-separator">:</span>
                                <select name="departureFlightArrivalMinute" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!--  /  -->
                    <div class="form-row-split">
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="departureFlightDeparturePoint" class="text-input" placeholder=": ">
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="departureFlightDestination" class="text-input" placeholder=":  ">
                        </div>
                    </div>

                    <h3 class="subsection-title"></h3>

                    <!--    -->
                    <div class="form-field-group">
                        <label class="field-label"></label>
                        <input type="text" name="returnFlightNumber" class="text-input" placeholder=": KE124">
                    </div>

                    <!--   /   -->
                    <div class="form-row-split">
                        <div class="form-field-group">
                            <label class="field-label"> </label>
                            <div class="time-picker-group">
                                <select name="returnFlightDepartureHour" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<24; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-separator">:</span>
                                <select name="returnFlightDepartureMinute" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"> </label>
                            <div class="time-picker-group">
                                <select name="returnFlightArrivalHour" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<24; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="time-separator">:</span>
                                <select name="returnFlightArrivalMinute" class="time-select">
                                    <option value="">00</option>
                                    <?php for($i=0; $i<60; $i+=5): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!--  /  -->
                    <div class="form-row-split">
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="returnFlightDeparturePoint" class="text-input" placeholder=":  ">
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="returnFlightDestination" class="text-input" placeholder=": ">
                        </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"> </h2>

                    <h3 class="subsection-title">  </h3>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="date-input-wrapper">
                            <input type="date" name="salesPeriod" class="text-input date-input" required>
                            <svg class="calendar-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <rect x="3" y="4" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M3 8H17" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M7 2V6M13 2V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <p class="field-hint">  100   .</p>
                    </div>

                    <!--    /    /    -->
                    <div class="form-row-triple">
                        <div class="form-field-group">
                            <label class="field-label required">  </label>
                            <div class="input-with-unit">
                                <input type="number" name="minParticipants" class="text-input" min="1" required>
                                <span class="input-unit"></span>
                            </div>
                        </div>
                        <div class="form-field-group">
                            <label class="field-label required">  </label>
                            <div class="input-with-unit">
                                <input type="number" name="maxParticipants" class="text-input" min="1" required>
                                <span class="input-unit"></span>
                            </div>
                        </div>
                        <div class="form-field-group">
                            <label class="field-label">   (₱)</label>
                            <input type="number" name="basePrice" class="text-input" min="0" step="0.01">
                        </div>
                    </div>
                    <p class="field-hint">     .</p>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"> </h2>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label"> (₱)</label>
                        <input type="number" name="airfareCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label"> (₱)</label>
                        <input type="number" name="accommodationCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label"> (₱)</label>
                        <input type="number" name="mealCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label">  (₱)</label>
                        <input type="number" name="guideCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label">  (₱)</label>
                        <input type="number" name="vehicleCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label"> (₱)</label>
                        <input type="number" name="entranceCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label">  (₱)</label>
                        <input type="number" name="otherCost" class="text-input" min="0" step="0.01" placeholder="0.00">
                    </div>

                    <!--   ( ) -->
                    <div class="form-field-group">
                        <label class="field-label">  (₱)</label>
                        <input type="number" name="totalCost" class="text-input" min="0" step="0.01" placeholder="0.00" readonly style="background-color: #f5f5f5;">
                    </div>
                    <p class="field-hint">      .</p>

                    <!--  -->
                    <div class="section-divider"></div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h2 class="section-title" style="margin: 0;"></h2>
                        <button type="button" class="btn-outline" onclick="addDaySchedule()" style="background-color: #4CAF50; color: white; border-color: #4CAF50;">+ Add day</button>
                    </div>

                    <h3 class="subsection-title"> </h3>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="time-picker-group">
                            <select name="meetingHour" class="time-select" required>
                                <option value="">00</option>
                                <?php for($i=0; $i<24; $i++): ?>
                                <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="time-separator">:</span>
                            <select name="meetingMinute" class="time-select" required>
                                <option value="">00</option>
                                <?php for($i=0; $i<60; $i+=5): ?>
                                <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <input type="text" name="meetingLocation" class="text-input" placeholder="  " required>
                    </div>

                    <!--    -->
                    <div class="form-field-group">
                        <label class="field-label required">  </label>
                        <input type="text" name="meetingAddress" class="text-input" placeholder="   " required>
                    </div>

                    <!--    -->
                    <div id="daySchedulesContainer">
                        <!-- 1Day  -->
                        <div class="day-schedule-section" data-day-number="1">
                            <div class="day-header">
                                <span class="day-title">1Day</span>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="toggle-day-btn">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="delete-day-btn" onclick="deleteDaySchedule(this)" style="background: none; border: none; cursor: pointer; padding: 4px; color: #ff4444;" title=" ">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                        <div class="day-content">
                            <!--   -->
                            <div class="form-field-group">
                                <label class="field-label"> </label>
                                <input type="text" name="day1_description" class="text-input" placeholder=" ">
                            </div>

                            <!--   -->
                            <div class="form-field-group">
                                <label class="field-label"> </label>
                                <button type="button" class="add-attraction-btn">+  </button>
                            </div>

                            <!--    /   -->
                            <div class="form-row-split">
                                <div class="form-field-group">
                                    <label class="field-label required">  </label>
                                    <div class="time-picker-group">
                                        <select name="day1_startHour" class="time-select" required>
                                            <option value="">00</option>
                                            <?php for($i=0; $i<24; $i++): ?>
                                            <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <span class="time-separator">:</span>
                                        <select name="day1_startMinute" class="time-select" required>
                                            <option value="">00</option>
                                            <?php for($i=0; $i<60; $i+=5): ?>
                                            <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-field-group">
                                    <label class="field-label required">  </label>
                                    <div class="time-picker-group">
                                        <select name="day1_endHour" class="time-select" required>
                                            <option value="">00</option>
                                            <?php for($i=0; $i<24; $i++): ?>
                                            <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <span class="time-separator">:</span>
                                        <select name="day1_endMinute" class="time-select" required>
                                            <option value="">00</option>
                                            <?php for($i=0; $i<60; $i+=5): ?>
                                            <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!--  -->
                            <div class="form-field-group">
                                <label class="field-label required"></label>
                                <input type="text" name="day1_airport" class="text-input" placeholder=" " required>
                            </div>

                            <!--   -->
                            <div class="form-field-group">
                                <label class="field-label required"> </label>
                                <input type="text" name="day1_airportAddress" class="text-input" placeholder="  " required>
                            </div>

                            <!--   -->
                            <div class="form-field-group">
                                <label class="field-label required"> </label>
                                <div class="rich-editor">
                                    <div class="editor-toolbar">
                                        <select class="toolbar-select">
                                            <option></option>
                                        </select>
                                        <select class="toolbar-select">
                                            <option>Arial</option>
                                        </select>
                                        <select class="toolbar-select">
                                            <option>15</option>
                                        </select>
                                        <div class="toolbar-divider"></div>
                                        <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                        <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                        <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                        <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                        <div class="toolbar-divider"></div>
                                        <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                        <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                        <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                        <div class="toolbar-divider"></div>
                                        <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                        <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                                    </div>
                                    <div class="editor-content" contenteditable="true" data-placeholder="  "></div>
                                </div>
                            </div>

                            <!--   -->
                            <div class="form-field-group">
                                <label class="field-label required"> </label>
                                <div class="image-upload-box single day-airport-image-upload" id="day1_airportImageUpload">
                                    <div class="upload-btn-wrapper">
                                        <button type="button" class="upload-trigger-btn">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                             
                                        </button>
                                    </div>
                                    <input type="file" class="day-airport-image-input" id="day1_airportImageInput" accept="image/*" hidden>
                                    <div class="image-preview day-airport-image-preview" id="day1_airportImagePreview" style="display:none;"></div>
                                </div>
                                <button type="button" class="outline-btn mt-2"> </button>
                            </div>
                        </div>
                    </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h3 class="subsection-title"> </h3>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <input type="text" name="accommodationName" class="text-input" placeholder=" " required>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <input type="text" name="accommodationAddress" class="text-input" placeholder="  " required>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label required"> </label>
                        <div class="image-upload-box single" id="accommodationImageUpload">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="upload-trigger-btn">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                     
                                </button>
                            </div>
                            <input type="file" id="accommodationImageInput" accept="image/*" hidden>
                            <div class="image-preview" id="accommodationImagePreview" style="display:none;"></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h3 class="subsection-title"> </h3>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label"></label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h3 class="subsection-title"> </h3>

                    <!--  /  /  -->
                    <div class="form-row-triple">
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="breakfast" class="text-input" placeholder="">
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="lunch" class="text-input" placeholder="">
                        </div>
                        <div class="form-field-group">
                            <label class="field-label"></label>
                            <input type="text" name="dinner" class="text-input" placeholder="">
                        </div>
                    </div>

                    <!--   -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"> </h2>

                    <h3 class="subsection-title"> </h3>

                    <!--    -->
                    <div class="pricing-table-wrapper">
                        <table class="pricing-table" id="pricingTable">
                            <thead>
                                <tr>
                                    <th width="60">No</th>
                                    <th></th>
                                    <th>  (₱)</th>
                                    <th width="80"></th>
                                </tr>
                            </thead>
                            <tbody id="pricingTableBody">
                                <tr>
                                    <td class="row-number">1</td>
                                    <td>
                                        <input type="text" name="optionName[]" class="table-input" placeholder="">
                                    </td>
                                    <td>
                                        <input type="number" name="optionPrice[]" class="table-input" placeholder=" " min="0" step="0.01">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="delete-row-btn" onclick="deleteRow(this)">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M6 6L14 14M14 6L6 14" stroke="#666" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-row-btn" onclick="addPricingRow()">+ </button>
                    </div>

                    <!--    -->
                    <div class="form-field-group mt-3">
                        <label class="field-label">  </label>
                        <select name="productPricingType" class="select-input">
                            <option value=""></option>
                            <option value="per_person">1</option>
                            <option value="per_group"></option>
                            <option value="per_day">1</option>
                        </select>
                    </div>

                    <!--    (₱) -->
                    <div class="form-field-group">
                        <label class="field-label">   (₱)</label>
                        <input type="number" name="productPricing" class="text-input" placeholder=" " min="0" step="0.01">
                        <p class="field-hint orange">*       . 1    .</p>
                    </div>

                    <!--  /   -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"> /  </h2>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label"> </label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder="  . :  , ,  "></div>
                        </div>
                    </div>

                    <!--   -->
                    <div class="form-field-group">
                        <label class="field-label"> </label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder="  . :  ,  ,   "></div>
                        </div>
                    </div>

                    <!--  -->
                    <div class="section-divider"></div>
                    <h2 class="section-title"></h2>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <div class="file-upload-wrapper">
                            <label class="file-upload-btn">
                                <input type="checkbox" name="fileUploadCheck" class="file-upload-checkbox">
                                <span class="file-upload-label"> </span>
                            </label>
                            <input type="file" id="guideFileInput" accept=".pdf,.doc,.docx" hidden>
                        </div>
                    </div>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                    <!-- /  -->
                    <div class="section-divider"></div>
                    <h2 class="section-title">/ </h2>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                    <!--    -->
                    <div class="section-divider"></div>
                    <h2 class="section-title">  </h2>

                    <!--  -->
                    <div class="form-field-group">
                        <label class="field-label required"></label>
                        <div class="rich-editor">
                            <div class="editor-toolbar">
                                <select class="toolbar-select">
                                    <option></option>
                                </select>
                                <select class="toolbar-select">
                                    <option>Arial</option>
                                </select>
                                <select class="toolbar-select">
                                    <option>15</option>
                                </select>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="bold"><strong>B</strong></button>
                                <button type="button" class="toolbar-btn" data-cmd="italic"><em>I</em></button>
                                <button type="button" class="toolbar-btn" data-cmd="underline"><u>U</u></button>
                                <button type="button" class="toolbar-btn" data-cmd="foreColor">A</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="justifyLeft">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyCenter">≡</button>
                                <button type="button" class="toolbar-btn" data-cmd="justifyRight">≡</button>
                                <div class="toolbar-divider"></div>
                                <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList">•</button>
                                <button type="button" class="toolbar-btn" data-cmd="insertLink">🔗</button>
                            </div>
                            <div class="editor-content" contenteditable="true" data-placeholder=" "></div>
                        </div>
                    </div>

                </form>
            </div>
        </main>
    </div>

    <script src="assets/js/admin-productRegisterNew.js"></script>
</body>
</html>
