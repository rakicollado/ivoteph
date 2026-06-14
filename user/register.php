<?php
if (session_id() == '') {
    session_start();
}

if (isset($_SESSION['voter_id']) && $_SESSION['voter_id'] != '') {
    header('Location: index.php');
    exit();
}

$error_message = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] == 'empty') {
        $error_message = 'Please complete all required fields.';
    } elseif ($_GET['error'] == 'password_mismatch') {
        $error_message = 'Passwords do not match.';
    } elseif ($_GET['error'] == 'weak_password') {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($_GET['error'] == 'invalid_voter') {
        $error_message = 'Voter ID does not exist in the official voter database.';
    } elseif ($_GET['error'] == 'already_registered') {
        $error_message = 'This Voter ID already has an account. Please log in instead.';
    } elseif ($_GET['error'] == 'email_exists') {
        $error_message = 'This email address is already used by another voter.';
    } elseif ($_GET['error'] == 'mobile_exists') {
        $error_message = 'This mobile number is already used by another voter account.';
    } elseif ($_GET['error'] == 'duplicate_credentials') {
        $error_message = 'The details you entered match an existing voter account. Please log in instead.';
    } elseif ($_GET['error'] == 'not_certified') {
        $error_message = 'You must certify that the information you provided is accurate and complete.';
    } elseif ($_GET['error'] == 'identity_mismatch') {
        $error_message = 'The information you entered does not match the official voter record for that Voter ID.';
    } elseif ($_GET['error'] == 'server_error') {
        $error_message = 'A server error occurred. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - iVotePH</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        img.brandLogo {
            width: 126px !important;
            max-width: 126px !important;
            height: auto !important;
            max-height: 46px !important;
            object-fit: contain !important;
            display: block !important;
        }

        .userNavList,
        .sidebarMenuNav {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .userNavList {
            display: flex !important;
            gap: 8px !important;
            overflow-x: auto !important;
        }

        .userNavList li,
        .sidebarMenuNav li {
            list-style: none !important;
        }

        .userTopbarInner {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
        }

        .userSidebar,
        #sidebar {
            transform: translateX(-110%);
        }

        .userSidebar.open,
        #sidebar.open {
            transform: translateX(0);
        }

        .registerAlert {
            border-radius: 16px;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 16px;
            padding: 13px 14px;
        }

        .passwordNote {
            font-size: 12px;
            color: #667085;
            margin-top: 6px;
        }
    </style>
</head>

<body class="authPage">
    <div class="registerContainer">
        <div class="registerCard card">
            <div class="authLogo">
                <img src="FINALS 2.png" alt="iVotePH" class="authLogoImg">
            </div>

            <h1>Register</h1>
            <p class="registerSubtitle">Create your voter account using your existing Voter ID</p>

            <?php if ($error_message != '') { ?>
                <div class="alert alert-danger registerAlert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php } ?>

            <form id="registerForm" action="register_process.php" method="POST">
                <div class="formSection">
                    <div class="formSectionTitle">Voter Verification</div>

                    <div class="formGroup">
                        <label for="voter_id" class="formLabel">Voter ID</label>
                        <input 
                            id="voter_id" 
                            name="voter_id" 
                            type="text" 
                            class="formInput" 
                            placeholder="Enter existing Voter ID" 
                            required>
                    </div>
                </div>

                <div class="formSection">
                    <div class="formSectionTitle">Personal Information</div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="first_name" class="formLabel">First Name</label>
                                <input id="first_name" name="first_name" type="text" class="formInput" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="middle_name" class="formLabel">Middle Name</label>
                                <input id="middle_name" name="middle_name" type="text" class="formInput">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="last_name" class="formLabel">Last Name</label>
                                <input id="last_name" name="last_name" type="text" class="formInput" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="birth_date" class="formLabel">Birthdate</label>
                                <input id="birth_date" name="birth_date" type="date" class="formInput" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="sex" class="formLabel">Sex</label>
                                <select id="sex" name="sex" class="formSelect" required>
                                    <option value="">Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="formGroup">
                                <label for="mobile_number" class="formLabel">Mobile Number</label>
                                <div class="phoneInputWrapper">
                                    <div class="phonePrefix">+63</div>
                                    <input 
                                        id="mobile_number" 
                                        name="mobile_number" 
                                        type="tel" 
                                        class="formInput" 
                                        placeholder="9123456789" 
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="formGroup">
                        <label for="email" class="formLabel">Email Address</label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            class="formInput" 
                            placeholder="example@email.com" 
                            required>
                    </div>
                </div>

                <div class="formSection addressSection">
                    <div class="formSectionTitle">Address Information</div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="region" class="formLabel">Region</label>
                                <select id="region" name="region" class="formSelect" required>
                                    <option value="">Loading regions...</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="province" class="formLabel">Province</label>
                                <select id="province" name="province" class="formSelect" required disabled>
                                    <option value="">Select region first</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="city_municipality" class="formLabel">City / Municipality</label>
                                <select id="city_municipality" name="city_municipality" class="formSelect" required disabled>
                                    <option value="">Select province first</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="barangay" class="formLabel">Barangay</label>
                                <input id="barangay" name="barangay" type="text" class="formInput" placeholder="Enter barangay" required>
                            </div>
                        </div>
                    </div>

                    <div class="formGroup">
                        <label for="specific_address" class="formLabel">Specific Address</label>
                        <input id="specific_address" name="specific_address" type="text" class="formInput" placeholder="House no., street, subdivision, etc." required>
                    </div>
                </div>

                <div class="formSection">
                    <div class="formSectionTitle">Account Security</div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="password" class="formLabel">Password</label>
                                <input id="password" name="password" type="password" class="formInput" required>
                                <div class="passwordNote">Minimum of 8 characters.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="formGroup">
                                <label for="confirm_password" class="formLabel">Confirm Password</label>
                                <input id="confirm_password" name="confirm_password" type="password" class="formInput" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="formCheckbox">
                    <input type="checkbox" id="certify" name="certify" value="1" required>
                    <label for="certify">I certify that the information I provided is accurate and complete.</label>
                </div>

                <button type="submit" class="registerBtn btn w-100 py-3">
                    <i class="fa-solid fa-user-plus me-2"></i>
                    Create Account
                </button>
            </form>

            <div class="loginLink text-center mt-3">
                Already registered?
                <a href="login.php">Log in</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var regionSelect = document.getElementById('region');
            var provinceSelect = document.getElementById('province');
            var citySelect = document.getElementById('city_municipality');
            var registerForm = document.getElementById('registerForm');

            var psgcBaseUrl = 'https://psgc.gitlab.io/api';

            var fallbackRegions = [
                { code: '010000000', name: 'Ilocos Region', regionName: 'Region I' },
                { code: '020000000', name: 'Cagayan Valley', regionName: 'Region II' },
                { code: '030000000', name: 'Central Luzon', regionName: 'Region III' },
                { code: '040000000', name: 'CALABARZON', regionName: 'Region IV-A' },
                { code: '170000000', name: 'MIMAROPA Region', regionName: 'MIMAROPA Region' },
                { code: '050000000', name: 'Bicol Region', regionName: 'Region V' },
                { code: '060000000', name: 'Western Visayas', regionName: 'Region VI' },
                { code: '070000000', name: 'Central Visayas', regionName: 'Region VII' },
                { code: '080000000', name: 'Eastern Visayas', regionName: 'Region VIII' },
                { code: '090000000', name: 'Zamboanga Peninsula', regionName: 'Region IX' },
                { code: '100000000', name: 'Northern Mindanao', regionName: 'Region X' },
                { code: '110000000', name: 'Davao Region', regionName: 'Region XI' },
                { code: '120000000', name: 'SOCCSKSARGEN', regionName: 'Region XII' },
                { code: '130000000', name: 'NCR', regionName: 'National Capital Region' },
                { code: '140000000', name: 'CAR', regionName: 'Cordillera Administrative Region' },
                { code: '160000000', name: 'Caraga', regionName: 'Region XIII' },
                { code: '150000000', name: 'BARMM', regionName: 'Bangsamoro Autonomous Region in Muslim Mindanao' }
            ];

            function getRegionDisplayName(region) {
                if (region.regionName && region.name && region.regionName != region.name) {
                    return region.name + ', ' + region.regionName;
                }

                return region.name || region.regionName || '';
            }

            function clearSelect(selectElement, placeholderText) {
                selectElement.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = placeholderText;
                selectElement.appendChild(placeholder);
            }

            function sortByName(rows) {
                rows.sort(function (a, b) {
                    var nameA = (a.name || '').toLowerCase();
                    var nameB = (b.name || '').toLowerCase();

                    if (nameA < nameB) {
                        return -1;
                    }

                    if (nameA > nameB) {
                        return 1;
                    }

                    return 0;
                });

                return rows;
            }

            function fetchJson(url) {
                return fetch(url, { cache: 'force-cache' }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to load address list.');
                    }

                    return response.json();
                });
            }

            function loadRegions() {
                clearSelect(regionSelect, 'Select Region');
                clearSelect(provinceSelect, 'Select region first');
                clearSelect(citySelect, 'Select province first');

                provinceSelect.disabled = true;
                citySelect.disabled = true;

                fetchJson(psgcBaseUrl + '/regions/')
                    .then(function (regions) {
                        renderRegions(regions);
                    })
                    .catch(function () {
                        renderRegions(fallbackRegions);
                    });
            }

            function renderRegions(regions) {
                clearSelect(regionSelect, 'Select Region');

                regions.forEach(function (region) {
                    var option = document.createElement('option');
                    option.value = getRegionDisplayName(region);
                    option.textContent = getRegionDisplayName(region);
                    option.setAttribute('data-code', region.code);
                    regionSelect.appendChild(option);
                });
            }

            function loadProvinces(regionCode) {
                clearSelect(provinceSelect, 'Loading provinces...');
                clearSelect(citySelect, 'Select province first');

                provinceSelect.disabled = true;
                citySelect.disabled = true;

                if (!regionCode) {
                    clearSelect(provinceSelect, 'Select region first');
                    return;
                }

                fetchJson(psgcBaseUrl + '/regions/' + encodeURIComponent(regionCode) + '/provinces/')
                    .then(function (provinces) {
                        if (!provinces || provinces.length == 0) {
                            renderNcrProvince(regionCode);
                            return;
                        }

                        renderProvinces(sortByName(provinces), false, regionCode);
                    })
                    .catch(function () {
                        clearSelect(provinceSelect, 'Unable to load provinces');
                        provinceSelect.disabled = true;
                    });
            }

            function renderNcrProvince(regionCode) {
                clearSelect(provinceSelect, 'Select Province');

                var option = document.createElement('option');
                option.value = 'Metro Manila';
                option.textContent = 'Metro Manila';
                option.setAttribute('data-code', regionCode);
                option.setAttribute('data-region-only', '1');
                provinceSelect.appendChild(option);

                provinceSelect.disabled = false;
            }

            function renderProvinces(provinces, isRegionOnly, regionCode) {
                clearSelect(provinceSelect, 'Select Province');

                provinces.forEach(function (province) {
                    var option = document.createElement('option');
                    option.value = province.name;
                    option.textContent = province.name;
                    option.setAttribute('data-code', province.code);

                    if (isRegionOnly) {
                        option.setAttribute('data-region-only', '1');
                        option.setAttribute('data-region-code', regionCode);
                    }

                    provinceSelect.appendChild(option);
                });

                provinceSelect.disabled = false;
            }

            function loadCities() {
                var selectedProvinceOption = provinceSelect.options[provinceSelect.selectedIndex];
                var provinceCode = selectedProvinceOption ? selectedProvinceOption.getAttribute('data-code') : '';
                var isRegionOnly = selectedProvinceOption ? selectedProvinceOption.getAttribute('data-region-only') : '';
                var endpoint = '';

                clearSelect(citySelect, 'Loading cities / municipalities...');
                citySelect.disabled = true;

                if (!provinceCode) {
                    clearSelect(citySelect, 'Select province first');
                    return;
                }

                if (isRegionOnly == '1') {
                    endpoint = psgcBaseUrl + '/regions/' + encodeURIComponent(provinceCode) + '/cities-municipalities/';
                } else {
                    endpoint = psgcBaseUrl + '/provinces/' + encodeURIComponent(provinceCode) + '/cities-municipalities/';
                }

                fetchJson(endpoint)
                    .then(function (cities) {
                        renderCities(sortByName(cities));
                    })
                    .catch(function () {
                        clearSelect(citySelect, 'Unable to load cities / municipalities');
                        citySelect.disabled = true;
                    });
            }

            function renderCities(cities) {
                clearSelect(citySelect, 'Select City / Municipality');

                cities.forEach(function (city) {
                    var option = document.createElement('option');
                    option.value = city.name;
                    option.textContent = city.name;
                    option.setAttribute('data-code', city.code);
                    citySelect.appendChild(option);
                });

                citySelect.disabled = false;
            }

            regionSelect.addEventListener('change', function () {
                var selectedOption = regionSelect.options[regionSelect.selectedIndex];
                var regionCode = selectedOption ? selectedOption.getAttribute('data-code') : '';

                loadProvinces(regionCode);
            });

            provinceSelect.addEventListener('change', function () {
                loadCities();
            });

            registerForm.addEventListener('submit', function (e) {
                var password = document.getElementById('password').value;
                var confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }

                if (!document.getElementById('certify').checked) {
                    e.preventDefault();
                    alert('You must certify that the information you provided is accurate and complete.');
                    return false;
                }

                if (regionSelect.value == '' || provinceSelect.value == '' || citySelect.value == '') {
                    e.preventDefault();
                    alert('Please select your region, province, and city / municipality.');
                    return false;
                }
            });

            loadRegions();
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>