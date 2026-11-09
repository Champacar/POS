<?php
/**
 * Users API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $currentUserRole = $_SESSION['role'] ?? '';
            if ($id > 0) {
                $stmt = $db->prepare("SELECT id, username, full_name, role, permissions, phone, email, is_active, last_login, created_at FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user && $user['role'] === 'developer' && $currentUserRole !== 'developer') {
                    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ']);
                } else if ($user) {
                    $user['permissions'] = !empty($user['permissions']) ? json_decode($user['permissions'], true) : getDefaultPermissionsByRole($user['role']);
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຜູ້ໃຊ້']);
                }
            } else {
                if ($currentUserRole === 'developer') {
                    $stmt = $db->query("SELECT id, username, full_name, role, permissions, phone, email, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
                } else {
                    $stmt = $db->query("SELECT id, username, full_name, role, permissions, phone, email, is_active, last_login, created_at FROM users WHERE role != 'developer' ORDER BY created_at DESC");
                }
                $users = $stmt->fetchAll();
                foreach ($users as &$u) {
                    $u['permissions'] = !empty($u['permissions']) ? json_decode($u['permissions'], true) : getDefaultPermissionsByRole($u['role']);
                }
                echo json_encode(['success' => true, 'users' => $users]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $currentUserRole = $_SESSION['role'] ?? '';
            
            // Check authorization if target user is developer or target role is developer
            if (!empty($input['id'])) {
                $stmtCheckDev = $db->prepare("SELECT role FROM users WHERE id = ?");
                $stmtCheckDev->execute([(int)$input['id']]);
                $targetRole = $stmtCheckDev->fetchColumn();
                if ($targetRole === 'developer' && $currentUserRole !== 'developer') {
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
            if (isset($input['permissions']) && is_array($input['permissions'])) {
                $permissionsJson = json_encode($input['permissions'], JSON_UNESCAPED_UNICODE);
            }
            
            if (!empty($input['id'])) {
                // Update
                $id = (int)$input['id'];
                if ($id === 1 && $input['is_active'] == 0) {
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
                
                if (!empty($input['password'])) {
                    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, permissions = ?, phone = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        $input['username'],
                        $passwordHash,
                        $input['full_name'],
                        $role,
                        $permissionsJson,
                        $input['phone'] ?? null,
                        $input['is_active'] ?? 1,
                        $id
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, permissions = ?, phone = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        $input['username'],
                        $input['full_name'],
                        $role,
                        $permissionsJson,
                        $input['phone'] ?? null,
                        $input['is_active'] ?? 1,
                        $id
                    ]);
                }

                if ($id === (int)($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['username'] = $input['username'];
                    $_SESSION['full_name'] = $input['full_name'];
                    $_SESSION['role'] = $role;
                    if (isset($input['permissions']) && is_array($input['permissions'])) {
                        $_SESSION['permissions'] = $input['permissions'];
                    }
                }

                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂຂໍ້ມູນພະນັກງານສຳເລັດ']);
            } else {
                // Insert
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
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, permissions, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['username'],
                    $passwordHash,
                    $input['full_name'],
                    $role,
                    $permissionsJson,
                    $input['phone'] ?? null,
                    $input['is_active'] ?? 1
                ]);
                echo json_encode(['success' => true, 'message' => 'ເພີ່ມພະນັກງານສຳເລັດ', 'id' => $db->lastInsertId()]);
            }
            break;
            
        case 'DELETE':
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
            $currentUserRole = $_SESSION['role'] ?? '';
            $stmtCheckDev = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmtCheckDev->execute([$id]);
            $targetRole = $stmtCheckDev->fetchColumn();
            if ($targetRole === 'developer' && $currentUserRole !== 'developer') {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດຈັດການຜູ້ໃຊ້ລະດັບນັກພັດທະນາ']);
                exit;
            }
            
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບພະນັກງານສຳເລັດ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
