<?php
/**
 * Users & Profile API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Handle avatar image upload securely
 */
function handleAvatarUpload($file, $userId, $currentAvatar = null) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception('ຮອງຮັບສະເພາະໄຟລ໌ຮູບພາບ (JPG, PNG, WEBP, GIF)');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 5MB');
    }

    $ext = $allowedTypes[$mimeType];
    $filename = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $targetDir = __DIR__ . '/../assets/images/avatars/';

    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('ບໍ່ສາມາດບັນທຶກໄຟລ໌ຮູບພາບໄດ້');
    }

    // Sync to desktop app directories if they exist
    $syncDirs = [
        'D:/Project/pospharma/assets/images/avatars/',
        'D:/Project/pospharma/desktop-app/src/assets/images/avatars/',
        'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/assets/images/avatars/'
    ];
    foreach ($syncDirs as $dir) {
        if (is_dir($dir) && realpath($dir) !== realpath($targetDir)) {
            @copy($targetPath, $dir . $filename);
        }
    }

    // Delete old avatar file if present
    if (!empty($currentAvatar)) {
        deleteAvatarFile($currentAvatar);
    }

    return $filename;
}

/**
 * Remove avatar file from server & synced dirs
 */
function deleteAvatarFile($avatarFilename) {
    if (empty($avatarFilename)) return;
    $clean = basename($avatarFilename);
    $dirs = [
        __DIR__ . '/../assets/images/avatars/',
        'D:/Project/pospharma/assets/images/avatars/',
        'D:/Project/pospharma/desktop-app/src/assets/images/avatars/',
        'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/assets/images/avatars/'
    ];
    foreach ($dirs as $dir) {
        $path = $dir . $clean;
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}

try {
    $db = getDB();
    $currentUserRole = $_SESSION['role'] ?? '';
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            // 1. Get current logged-in user profile
            if ($action === 'current_user' || $action === 'profile') {
                $stmt = $db->prepare("SELECT id, username, full_name, role, permissions, phone, email, avatar, is_active, last_login, created_at FROM users WHERE id = ?");
                $stmt->execute([$currentUserId]);
                $user = $stmt->fetch();
                if ($user) {
                    $user['permissions'] = !empty($user['permissions']) ? json_decode($user['permissions'], true) : getDefaultPermissionsByRole($user['role']);
                    $user['avatar_url'] = getUserAvatarUrl($user['avatar']);
                    $user['role_label'] = getRoleLabel($user['role']);
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້']);
                }
                exit;
            }

            // 2. Get specific user by ID (Staff management)
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT id, username, full_name, role, permissions, phone, email, avatar, is_active, last_login, created_at FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user && $user['role'] === 'developer' && $currentUserRole !== 'developer') {
                    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ']);
                } else if ($user) {
                    $user['permissions'] = !empty($user['permissions']) ? json_decode($user['permissions'], true) : getDefaultPermissionsByRole($user['role']);
                    $user['avatar_url'] = getUserAvatarUrl($user['avatar']);
                    $user['role_label'] = getRoleLabel($user['role']);
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຜູ້ໃຊ້']);
                }
            } else {
                // List users (requires admin, manager, or developer)
                if (!in_array($currentUserRole, ['admin', 'manager', 'developer'], true)) {
                    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເບິ່ງລາຍຊື່ຜູ້ໃຊ້']);
                    exit;
                }
                if ($currentUserRole === 'developer') {
                    $stmt = $db->query("SELECT id, username, full_name, role, permissions, phone, email, avatar, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
                } else {
                    $stmt = $db->query("SELECT id, username, full_name, role, permissions, phone, email, avatar, is_active, last_login, created_at FROM users WHERE role != 'developer' ORDER BY created_at DESC");
                }
                $users = $stmt->fetchAll();
                foreach ($users as &$u) {
                    $u['permissions'] = !empty($u['permissions']) ? json_decode($u['permissions'], true) : getDefaultPermissionsByRole($u['role']);
                    $u['avatar_url'] = getUserAvatarUrl($u['avatar']);
                    $u['role_label'] = getRoleLabel($u['role']);
                }
                echo json_encode(['success' => true, 'users' => $users]);
            }
            break;
            
        case 'POST':
            $input = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = [];
            }
            $action = $_GET['action'] ?? ($input['action'] ?? '');

            // ==========================================
            // ACTION: Update Current User's Own Profile
            // ==========================================
            if ($action === 'update_profile') {
                $fullName = trim($input['full_name'] ?? '');
                $phone = trim($input['phone'] ?? '');
                $email = trim($input['email'] ?? '');

                if (empty($fullName)) {
                    echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ ແລະ ນາມສະກຸນ']);
                    exit;
                }

                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ']);
                    exit;
                }

                // Fetch current user from DB
                $stmtUser = $db->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
                $stmtUser->execute([$currentUserId]);
                $currentUser = $stmtUser->fetch();
                if (!$currentUser) {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຜູ້ໃຊ້']);
                    exit;
                }

                $avatarFilename = $currentUser['avatar'];

                // Handle remove avatar
                if (!empty($input['remove_avatar']) && $input['remove_avatar'] == 1) {
                    deleteAvatarFile($currentUser['avatar']);
                    $avatarFilename = null;
                }

                // Handle new avatar upload
                if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $newAvatar = handleAvatarUpload($_FILES['avatar'], $currentUserId, $currentUser['avatar']);
                    if ($newAvatar) {
                        $avatarFilename = $newAvatar;
                    }
                }

                // Update database
                $stmtUpdate = $db->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, avatar = ? WHERE id = ?");
                $stmtUpdate->execute([$fullName, $phone ?: null, $email ?: null, $avatarFilename, $currentUserId]);

                // Update session
                $_SESSION['full_name'] = $fullName;
                $_SESSION['avatar'] = $avatarFilename;

                logActivity('profile_update', "ອັບເດດຂໍ້ມູນໂປຣໄຟລ: {$fullName}", 'user', [
                    'user_id' => $currentUserId,
                    'has_avatar' => !empty($avatarFilename)
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'ອັບເດດໂປຣໄຟລສຳເລັດແລ້ວ',
                    'user' => [
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'email' => $email,
                        'avatar' => $avatarFilename,
                        'avatar_url' => getUserAvatarUrl($avatarFilename)
                    ]
                ]);
                exit;
            }

            // ==========================================
            // ACTION: Change Current User's Password
            // ==========================================
            if ($action === 'change_password') {
                $currentPassword = $input['current_password'] ?? '';
                $newPassword = $input['new_password'] ?? '';
                $confirmPassword = $input['confirm_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນລະຫັດຜ່ານໃຫ້ຄົບຖ້ວນ']);
                    exit;
                }

                if (strlen($newPassword) < 6) {
                    echo json_encode(['success' => false, 'message' => 'ລະຫັດຜ່ານໃໝ່ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ']);
                    exit;
                }

                if ($newPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'message' => 'ການຢືນຢັນລະຫັດຜ່ານໃໝ່ບໍ່ກົງກັນ']);
                    exit;
                }

                $stmtUser = $db->prepare("SELECT password, username FROM users WHERE id = ?");
                $stmtUser->execute([$currentUserId]);
                $currentUser = $stmtUser->fetch();

                if (!$currentUser || !password_verify($currentPassword, $currentUser['password'])) {
                    echo json_encode(['success' => false, 'message' => 'ລະຫັດຜ່ານປະຈຸບັນບໍ່ຖືກຕ້ອງ']);
                    exit;
                }

                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdate = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$newHash, $currentUserId]);

                logActivity('password_change', "ປ່ຽນລະຫັດຜ່ານບັນຊີສຳເລັດ: {$currentUser['username']}", 'auth', [
                    'user_id' => $currentUserId
                ]);

                echo json_encode(['success' => true, 'message' => 'ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ']);
                exit;
            }

            // ==========================================
            // ACTION: Staff Management (Admin / Manager)
            // ==========================================
            if (!in_array($currentUserRole, ['admin', 'manager', 'developer'], true)) {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດຈັດການພະນັກງານ']);
                exit;
            }
            
            // Check authorization if target user is developer or target role is developer
            if (!empty($input['id'])) {
                $stmtCheckDev = $db->prepare("SELECT role, avatar FROM users WHERE id = ?");
                $stmtCheckDev->execute([(int)$input['id']]);
                $targetUser = $stmtCheckDev->fetch();
                if ($targetUser && $targetUser['role'] === 'developer' && $currentUserRole !== 'developer') {
                    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດຈັດການຜູ້ໃຊ້ລະດັບນັກພັດທະນາ']);
                    exit;
                }
            }
            if (isset($input['role']) && $input['role'] === 'developer' && $currentUserRole !== 'developer') {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດກຳນົດບົດບາດເປັນນັກພັດທະນາ']);
                exit;
            }
            
            if (empty($input['full_name']) || empty($input['username'])) {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນໃຫ້ຄົບ']);
                exit;
            }

            $allowedRoles = ['admin', 'pharmacist', 'manager', 'cashier'];
            if ($currentUserRole === 'developer') {
                $allowedRoles[] = 'developer';
            }
            $role = in_array($input['role'] ?? '', $allowedRoles, true) ? $input['role'] : 'cashier';

            $permissionsJson = null;
            if (isset($input['permissions'])) {
                if (is_array($input['permissions'])) {
                    $permissionsJson = json_encode($input['permissions'], JSON_UNESCAPED_UNICODE);
                } else if (is_string($input['permissions'])) {
                    $decoded = json_decode($input['permissions'], true);
                    if (is_array($decoded)) {
                        $permissionsJson = $input['permissions'];
                    }
                }
            }
            
            if (!empty($input['id'])) {
                // Update Staff
                $id = (int)$input['id'];
                if ($id === 1 && isset($input['is_active']) && (int)$input['is_active'] === 0) {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ສາມາດປິດໃຊ້ງານຜູ້ດູແລລະບົບຫຼັກໄດ້']);
                    exit;
                }
                
                // Check duplicate username for other users
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmtCheck->execute([$input['username'], $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'ຊື່ຜູ້ໃຊ້ນີ້ມີໃນລະບົບແລ້ວ']);
                    exit;
                }

                $avatarFilename = $targetUser['avatar'] ?? null;
                if (!empty($input['remove_avatar']) && $input['remove_avatar'] == 1) {
                    deleteAvatarFile($avatarFilename);
                    $avatarFilename = null;
                }
                if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $newAvatar = handleAvatarUpload($_FILES['avatar'], $id, $avatarFilename);
                    if ($newAvatar) {
                        $avatarFilename = $newAvatar;
                    }
                }
                
                if (!empty($input['password'])) {
                    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, permissions = ?, phone = ?, email = ?, avatar = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        $input['username'],
                        $passwordHash,
                        $input['full_name'],
                        $role,
                        $permissionsJson,
                        $input['phone'] ?? null,
                        $input['email'] ?? null,
                        $avatarFilename,
                        $input['is_active'] ?? 1,
                        $id
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, permissions = ?, phone = ?, email = ?, avatar = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        $input['username'],
                        $input['full_name'],
                        $role,
                        $permissionsJson,
                        $input['phone'] ?? null,
                        $input['email'] ?? null,
                        $avatarFilename,
                        $input['is_active'] ?? 1,
                        $id
                    ]);
                }

                if ($id === $currentUserId) {
                    $_SESSION['username'] = $input['username'];
                    $_SESSION['full_name'] = $input['full_name'];
                    $_SESSION['role'] = $role;
                    $_SESSION['avatar'] = $avatarFilename;
                    if (isset($input['permissions']) && is_array($input['permissions'])) {
                        $_SESSION['permissions'] = $input['permissions'];
                    }
                }

                logActivity('user_edit', "ແກ້ໄຂຂໍ້ມູນພະນັກງານ ID {$id}: {$input['username']} ({$input['full_name']})", 'settings', ['target_id' => $id, 'role' => $role]);
                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂຂໍ້ມູນພະນັກງານສຳເລັດ']);
            } else {
                // Insert New Staff
                if (empty($input['password'])) {
                    echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນລະຫັດຜ່ານ']);
                    exit;
                }
                
                // Check duplicate username
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmtCheck->execute([$input['username']]);
                if ($stmtCheck->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'ຊື່ຜູ້ໃຊ້ນີ້ມີໃນລະບົບແລ້ວ']);
                    exit;
                }
                
                $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, permissions, phone, email, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['username'],
                    $passwordHash,
                    $input['full_name'],
                    $role,
                    $permissionsJson,
                    $input['phone'] ?? null,
                    $input['email'] ?? null,
                    $input['is_active'] ?? 1
                ]);
                $newUserId = (int)$db->lastInsertId();

                if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $newAvatar = handleAvatarUpload($_FILES['avatar'], $newUserId, null);
                    if ($newAvatar) {
                        $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$newAvatar, $newUserId]);
                    }
                }

                logActivity('user_add', "ເພີ່ມພະນັກງານໃໝ່ ID {$newUserId}: {$input['username']} ({$input['full_name']}) ບົດບາດ: {$role}", 'settings', ['new_user_id' => $newUserId, 'role' => $role]);
                echo json_encode(['success' => true, 'message' => 'ເພີ່ມພະນັກງານສຳເລັດ', 'id' => $newUserId]);
            }
            break;
            
        case 'DELETE':
            if (!in_array($currentUserRole, ['admin', 'developer'], true)) {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດລຶບພະນັກງານ']);
                exit;
            }

            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            if ($id === 1) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ສາມາດລຶບຜູ້ດູແລລະບົບຫຼັກໄດ້']);
                exit;
            }
            
            // Check if target user is developer and caller is not
            $stmtCheckDev = $db->prepare("SELECT role, avatar FROM users WHERE id = ?");
            $stmtCheckDev->execute([$id]);
            $targetUser = $stmtCheckDev->fetch();
            if ($targetUser && $targetUser['role'] === 'developer' && $currentUserRole !== 'developer') {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດຈັດການຜູ້ໃຊ້ລະດັບນັກພັດທະນາ']);
                exit;
            }
            
            if ($targetUser && !empty($targetUser['avatar'])) {
                deleteAvatarFile($targetUser['avatar']);
            }

            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            logActivity('user_delete', "ລຶບພະນັກງານ ID {$id}", 'settings', ['target_id' => $id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບພະນັກງານສຳເລັດ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
