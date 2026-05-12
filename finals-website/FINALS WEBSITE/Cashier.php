<?php

//  Cashier.php — FiveSix Legazpi Cafe POS

session_start();
$conn = require_once 'databasetoSQL.php';

if (!isset($_SESSION['username'])) {
  header("Location: LOGIN.php");
  exit();
}

$login_host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$login_port = 19494;
$login_dbname = "login_credentials";
$login_user = "avnadmin";
$login_pass = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$login_conn = mysqli_connect($login_host, $login_user, $login_pass, $login_dbname, $login_port);
if (!$login_conn) {
  die("Login connection failed: " . mysqli_connect_error());
}

$username = $_SESSION['username'];
$query = "SELECT email, username, roles FROM employee_credentials WHERE username = ?";
$stmt = $login_conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: LOGIN.php");
  exit();
}

$user = $result->fetch_assoc();
$cashier_id   = $username;
$cashier_name = ucfirst($username);
$cashier_role = strtoupper($user['roles']);
$email = $user['email'];
$stmt->close();
$login_conn->close();


//  Handle POST requests (checkout, stock check)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';

  // ─── CHECKOUT ─────────────────────────────────────────
  if ($action === 'checkout') {
    $items        = json_decode($_POST['items'],        true);
    $discount_raw = json_decode($_POST['discount'],     true);
    $customer     = json_decode($_POST['customer'],     true);
    $cash         = floatval($_POST['cash_tendered']    ?? 0);

    if (!$items || !is_array($items)) {
      echo json_encode(['success' => false, 'message' => 'No items provided.']);
      exit;
    }

    // Calculate totals
    $subtotal        = 0;
    foreach ($items as $item) {
      $subtotal += ($item['price'] + $item['addonTotal']) * $item['qty'];
    }
    $disc_type   = $discount_raw['type']  ?? 'pct';
    $disc_value  = floatval($discount_raw['value'] ?? 0);
    $disc_amount = 0;
    if ($disc_value > 0) {
      $disc_amount = $disc_type === 'pct'
        ? round($subtotal * $disc_value / 100)
        : min($disc_value, $subtotal);
    }
    $total        = $subtotal - $disc_amount;
    $change_given = max(0, $cash - $total);

    // Generate order reference
    $order_ref = 'FS-' . strtoupper(substr(uniqid(), -6));

    // Begin transaction
    $conn->begin_transaction();
    try {
      // Insert order
      $stmt = $conn->prepare("
                INSERT INTO orders
                    (order_ref, cashier_id, customer_first, customer_last,
                     customer_contact, customer_address,
                     subtotal, discount_type, discount_value, discount_amount,
                     total, cash_tendered, change_given)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
      $stmt->bind_param(
        'sissssdsddddd',
        $order_ref,
        $cashier_id,
        $customer['first'],
        $customer['last'],
        $customer['contact'],
        $customer['address'],
        $subtotal,
        $disc_type,
        $disc_value,
        $disc_amount,
        $total,
        $cash,
        $change_given
      );
      $stmt->execute();
      $order_id = $conn->insert_id;

      foreach ($items as $item) {
        $line_total = ($item['price'] + $item['addonTotal']) * $item['qty'];

        // Insert order item
        $si = $conn->prepare("
                    INSERT INTO order_items
                        (order_id, product_id, product_name, unit_price,
                         addon_total, quantity, line_total, special_note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
        $si->bind_param(
          'iisddids',
          $order_id,
          $item['id'],
          $item['name'],
          $item['price'],
          $item['addonTotal'],
          $item['qty'],
          $line_total,
          $item['note']
        );
        $si->execute();
        $order_item_id = $conn->insert_id;

        // Insert add-ons for this item
        if (!empty($item['addons'])) {
          foreach ($item['addons'] as $addon) {
            $sa = $conn->prepare("
                            INSERT INTO order_item_addons
                                (order_item_id, addon_slug, addon_label, addon_price)
                            VALUES (?, ?, ?, ?)
                        ");
            $sa->bind_param('issd', $order_item_id, $addon['id'], $addon['label'], $addon['price']);
            $sa->execute();
          }
        }

        // ── Deduct stock <<<<<<<<<<<<<<<<──────────────────────
        $sd = $conn->prepare("
                    UPDATE products
                    SET stock = GREATEST(0, stock - ?)
                    WHERE product_id = ?
                ");
        $sd->bind_param('ii', $item['qty'], $item['id']);
        $sd->execute();
      }

      // Log the sale
      $log_action = "Sale recorded · {$order_ref}";
      $log_detail = count($items) . " item(s) · Total: ₱{$total} · Cash: ₱{$cash}";
      $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
      $sl = $conn->prepare("
                INSERT INTO activity_logs (user_id, user_name, type, action, detail, ref, ip_address, email)
                VALUES (?, ?, 'sale', ?, ?, ?, ?, ?)
            ");
      $sl->bind_param('issssss', $cashier_id, $cashier_name, $log_action, $log_detail, $order_ref, $ip, $email);
      $sl->execute();

      $conn->commit();

      echo json_encode([
        'success'     => true,
        'order_ref'   => $order_ref,
        'total'       => $total,
        'change'      => $change_given,
        'message'     => "Order {$order_ref} saved successfully!",
      ]);
    } catch (Exception $e) {
      $conn->rollback();
      echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
    }
    exit;
  }

  // ─── GET UPDATED PRODUCTS (refresh after sale) ────────
  if ($action === 'get_products') {
    $result = $conn->query("SELECT product_id As id, product_name As name, variant, category, price, stock, threshold, emoji FROM products WHERE is_active = 1 ORDER BY category, product_name");
    $products = [];
    while ($row = $result->fetch_assoc()) {
      $row['price']     = floatval($row['price']);
      $row['stock']     = intval($row['stock']);
      $row['threshold'] = intval($row['threshold']);
      $products[]       = $row;
    }
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
  }

  echo json_encode(['success' => false, 'message' => 'Unknown action.']);
  exit;
}


//  LOAD DATA for page render


// Products
$prod_result = $conn->query("
    SELECT product_id As id, product_name As name, variant, category, price, stock, threshold, emoji
    FROM products
    WHERE is_active = 1
    ORDER BY category, product_name
");
$products_php = [];
while ($row = $prod_result->fetch_assoc()) {
  $row['price']     = floatval($row['price']);
  $row['stock']     = intval($row['stock']);
  $row['threshold'] = intval($row['threshold']);
  $products_php[]   = $row;
}
$products_json = json_encode($products_php);

// Add-ons
$addon_result = $conn->query("SELECT slug AS id, label, price FROM addons WHERE is_active = 1");
$addons_php   = [];
while ($row = $addon_result->fetch_assoc()) {
  $row['price'] = floatval($row['price']);
  $addons_php[] = $row;
}
$addons_json = json_encode($addons_php);
?>
<!doctype html>
<html lang="en">

<head>
  <link rel="stylesheet" type="text/css" href="Cashier.css" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FiveSix Legazpi Cafe — POS</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
</head>

<body>

  <!-- HEADER -->
  <header>
    <div class="logo-area">
      <div class="logo-icon">☕</div>
      <span class="logo-name">FiveSix Legazpi Cafe</span>
    </div>
    <div class="search-bar">
      <span>🔍</span>
      <input
        type="text"
        id="searchInput"
        placeholder="Search products..."
        oninput="filterProducts()" />
    </div>
    <div class="header-right">
      <div class="user-area">
        <div class="user-icon">👤</div>
        <div class="user-info">
          <!-- PHP: show logged-in user -->
          <div class="user-name"><?= htmlspecialchars($cashier_name) ?></div>
          <div class="user-role"><?= htmlspecialchars($cashier_role) ?></div>
        </div>
        <div>
          <div class="user-date" id="liveDate"></div>
          <div class="user-date" id="liveTime"></div>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <div class="main">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <h3>Categories</h3>
      <button class="cat-btn active" onclick="setCategory('all', this)">All</button>
      <button class="cat-btn" onclick="setCategory('Espresso Based', this)">Espresso Based</button>
      <button class="cat-btn" onclick="setCategory('Non-Caffeine', this)">Non-Caffeine</button>
      <button class="cat-btn" onclick="setCategory('ADD-ONS', this)">ADD-ONS</button>
    </aside>

    <!-- PRODUCTS -->
    <div class="products-area">
      <div class="stock-legend">
        <span class="stock-legend-label">Stock:</span>
        <span class="legend-item"><span class="legend-dot" style="background:#3a7d54"></span>In Stock (&gt;10)</span>
        <span class="legend-item"><span class="legend-dot" style="background:#b07a10"></span>Low (6–10)</span>
        <span class="legend-item"><span class="legend-dot" style="background:#b04040"></span>Critical (1–5)</span>
        <span class="legend-item"><span class="legend-dot" style="background:#bbb"></span>Out of Stock</span>
      </div>
      <div class="products-grid" id="productsGrid"></div>
    </div>

    <!-- ORDER PANEL -->
    <div class="order-panel">
      <div class="order-header">
        <span class="order-title">Current Order</span>
        <span class="order-count" id="orderCount">0</span>
        <button class="clear-all-btn" onclick="clearAll()">Clear</button>
      </div>

        <!-- TABS -->
        <div class="panel-tabs">
          <button class="panel-tab active" id="tabCart" onclick="switchTab('cart')">🛒 Cart</button>
          <button class="panel-tab" id="tabReceipt" onclick="switchTab('receipt')">🧾 Receipt</button>
        </div>

        <!-- CART VIEW -->
        <div class="panel-view active" id="viewCart">
          <div class="order-items" id="orderItems">
            <div class="empty-order" id="emptyOrder">
              <div class="empty-icon">🛒</div>
              <div>No items yet</div>
            </div>
          </div>
          <div class="order-summary">
            <div class="summary-row"><span>Subtotal (VAT-incl.)</span><span id="subtotalVal">₱0.00</span></div>
            <div class="summary-row vat-row"><span>&nbsp;&nbsp;VAT 12% (included)</span><span id="vatVal">₱0.00</span></div>
            <div class="summary-row" id="discountRow" style="display:none;color:var(--green)">
              <span id="discLabel">Discount</span><span id="discountVal">-₱0.00</span>
            </div>
            <div class="summary-row total"><span>TOTAL:</span><span id="totalVal">₱0.00</span></div>
          </div>
          <div class="order-actions">
            <button class="discount-btn" onclick="openDiscount()">% Discount</button>
            <button class="checkout-btn" onclick="openCheckout()">🖨 Invoice &amp; Checkout</button>
          </div>
        </div>

        <!-- RECEIPT VIEW -->
        <div class="panel-view" id="viewReceipt">
          <div class="receipt-view">
            <div class="receipt-paper" id="receiptPaper">
              <div class="r-empty">Add items to<br>see live receipt ☕</div>
            </div>
            <button class="receipt-print-btn" id="printBtn" onclick="printReceipt()">
              🖨 Print Receipt
            </button>
          </div>
        </div>
    </div> <!-- closes order-panel -->

  </div> <!-- closes .main -->

  <!-- BOTTOM NAV -->
  <nav class="bottom-nav">
    <a class="nav-item" href="Merchandise.php"><span class="nav-icon">🖥</span>Dashboard</a>
    <a class="nav-item active" href="Cashier.php"><span class="nav-icon">📊</span>Sales</a>
    <a class="nav-item" href="Stocks.php"><span class="nav-icon">📦</span>Stocks</a>
    <a class="nav-item" href="Profile.php"><span class="nav-icon">👤</span>Profile</a>
    <a class="logout-btn" href="LOGIN.php">Log out ➜</a>
  </nav>

  <!-- ADD TO ORDER MODAL -->
  <div class="modal-overlay" id="addModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
      <div class="modal-head" id="modalHeadText">
        ADD TO ORDER: <span id="modalProductName" style="text-decoration:underline"></span>
      </div>
      <div style="font-size:13px;margin-bottom:10px;text-align:center;" id="modalBasePrice"></div>
      <div class="modal-stock-line" id="modalStockLine"></div>
      <div class="modal-body">
        <div class="modal-img" id="modalImg">☕</div>
        <div class="modal-details">
          <div class="modal-addons-label">↳ ADD-ONS:</div>
          <div id="addonsList"></div>
          <div class="modal-qty">
            <span>Quantity:</span>
            <button onclick="changeQty(-1)">−</button>
            <span id="modalQty">1</span>
            <button onclick="changeQty(1)">+</button>
          </div>
          <div class="modal-instructions">
            <label>Special Instructions:</label>
            <input type="text" id="specialInstructions" placeholder="e.g. less ice, no sugar..." />
          </div>
          <button class="modal-add-btn" onclick="confirmAdd()">Add</button>
        </div>
      </div>
    </div>
  </div>

  <!-- DISCOUNT MODAL -->
  <div class="modal-overlay" id="discountModal">
    <div class="modal disc-modal">
      <button class="modal-close" onclick="closeModal('discountModal')">✕</button>
      <div style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;margin-bottom:4px;">Apply Discount</div>
      <div style="font-size:12.5px;color:var(--muted);margin-bottom:12px;">Enter discount amount or percentage</div>
      <div class="disc-type">
        <button id="discPctBtn" class="active" onclick="setDiscType('pct')">% Percentage</button>
        <button id="discFixBtn" onclick="setDiscType('fix')">₱ Fixed</button>
      </div>
      <div class="disc-input-wrap">
        <input class="disc-input" type="number" id="discountInput" placeholder="0" min="0" oninput="previewDiscount()" />
        <span id="discUnit" style="font-size:18px;font-weight:700;color:var(--muted)">%</span>
      </div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:14px;">
        Discount: <b id="discPreview" style="color:var(--green)">₱0</b>
      </div>
      <button class="modal-add-btn" onclick="applyDiscount()">Apply Discount</button>
    </div>
  </div>

  <!-- CHECKOUT MODAL -->
  <div class="modal-overlay" id="checkoutModal">
    <div class="modal checkout-modal">
      <button class="modal-close" onclick="closeModal('checkoutModal')">✕</button>
      <div class="checkout-title">☕ CHECKOUT</div>
      <div class="checkout-cols">
        <div class="checkout-items-col">
          <h4>Items</h4>
          <table class="checkout-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Product Name</th>
                <th>Qty</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody id="checkoutTableBody"></tbody>
          </table>
        </div>
        <div class="checkout-pay-col">
          <div class="total-display">
            <div class="total-amount-big" id="checkoutTotal">₱0</div>
            <div class="total-label">Total Amount</div>
          </div>
          <div class="pay-input-row">
            <label>Cash:</label>
            <input type="number" class="pay-input" id="cashInput" placeholder="0" oninput="calcChange()" />
          </div>
          <div class="change-row" id="changeDisplay"></div>
          <div class="customer-fields">
            <h4 style="margin-top:4px;margin-bottom:10px;">Customer Details</h4>
            <label>First Name</label>
            <input type="text" id="custFirst" placeholder="Enter first name..." />
            <label>Last Name</label>
            <input type="text" id="custLast" placeholder="Enter last name..." />
            <label>Address</label>
            <input type="text" id="custAddress" placeholder="Enter address..." />
            <label>Contact</label>
            <input type="text" id="custContact" placeholder="Enter contact..." />
          </div>
          <div class="checkout-actions">
            <button class="cancel-btn" onclick="closeModal('checkoutModal')">Cancel</button>
            <button class="confirm-btn" onclick="confirmCheckout()">✓ Checkout</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast" id="toast"></div>

  <script>
    // ═══════════════════════════════════════════════════════
    //  DATA — injected by PHP from the database
    // ═══════════════════════════════════════════════════════
    let PRODUCTS = <?= $products_json ?>;
    const ADDONS = <?= $addons_json ?>;

    // ─── STATE ────────────────────────────────────────────
    let cart = [];
    let currentProduct = null;
    let modalQty = 1;
    let selectedAddons = [];
    let currentCategory = 'all';
    let discount = {
      type: 'pct',
      value: 0
    };
    let discType = 'pct';

    // ─── VAT HELPERS ──────────────────────────────────────
    const VAT_RATE = 0.12;

    function vatOf(amount) {
      return Math.round((amount * VAT_RATE / (1 + VAT_RATE)) * 100) / 100;
    }

    function fmtP(n) {
      return '₱' + (Math.round(n * 100) / 100).toFixed(2);
    }

    // ─── TAB SWITCH ───────────────────────────────────────
    function switchTab(tab) {
      document.getElementById('viewCart').classList.toggle('active', tab === 'cart');
      document.getElementById('viewReceipt').classList.toggle('active', tab === 'receipt');
      document.getElementById('tabCart').classList.toggle('active', tab === 'cart');
      document.getElementById('tabReceipt').classList.toggle('active', tab === 'receipt');
      if (tab === 'receipt') buildReceipt();
    }

    // ─── CLOCK ────────────────────────────────────────────
    function updateClock() {
      const now = new Date();
      document.getElementById('liveDate').textContent =
        now.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric'
        });
      document.getElementById('liveTime').textContent =
        now.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── STOCK HELPERS ────────────────────────────────────
    function getStockStatus(stock) {
      if (stock === 0) return {
        cls: 'stock-out',
        label: 'Out of Stock',
        barColor: '#bbb'
      };
      if (stock <= 5) return {
        cls: 'stock-crit',
        label: `${stock} left — Critical`,
        barColor: '#b04040'
      };
      if (stock <= 10) return {
        cls: 'stock-low',
        label: `${stock} left — Low`,
        barColor: '#b07a10'
      };
      return {
        cls: 'stock-ok',
        label: `${stock} in stock`,
        barColor: '#3a7d54'
      };
    }

    // ─── RENDER PRODUCTS ──────────────────────────────────
    function filterProducts() {
      const q = document.getElementById('searchInput').value.toLowerCase();
      renderProducts(q);
    }

    function setCategory(cat, btn) {
      currentCategory = cat;
      document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderProducts();
    }

    function renderProducts(q = '') {
      const grid = document.getElementById('productsGrid');
      const filtered = PRODUCTS.filter(p => {
        const matchCat = currentCategory === 'all' || p.category === currentCategory;
        const matchQ = !q || p.name.toLowerCase().includes(q) || p.variant.toLowerCase().includes(q);
        return matchCat && matchQ;
      });
      grid.innerHTML = filtered.map(p => {
        const st = getStockStatus(p.stock);
        const isOut = p.stock === 0;
        return `
      <div class="product-card${isOut ? ' out-of-stock' : ''}"
           onclick="${isOut ? '' : `openAddModal(${p.id})`}">
        <div class="product-img" style="position:relative;">
          ${p.emoji}
          ${isOut ? `<div style="position:absolute;inset:0;background:rgba(255,255,255,0.6);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">🚫</div>` : ''}
        </div>
        <div class="product-info">
          <div class="product-name">${p.name}</div>
          <div class="product-variant">(${p.variant})</div>
          <div class="product-price">₱${p.price}</div>
          <span class="stock-badge ${st.cls}">${st.label}</span>
        </div>
        <button class="add-btn"
          ${isOut ? 'disabled' : `onclick="event.stopPropagation();openAddModal(${p.id})"`}>
          ${isOut ? 'Unavailable' : 'Add'}
        </button>
      </div>`;
      }).join('') || '<div style="color:var(--muted);font-size:13px;padding:20px;">No products found.</div>';
    }
    renderProducts();

    // ─── ADD MODAL ────────────────────────────────────────
    function openAddModal(id) {
      currentProduct = PRODUCTS.find(p => p.id == id);
      if (!currentProduct || currentProduct.stock === 0) return;
      modalQty = 1;
      selectedAddons = [];
      const st = getStockStatus(currentProduct.stock);
      const maxStock = Math.max(currentProduct.stock, 30);
      const barPct = Math.round((currentProduct.stock / maxStock) * 100);
      document.getElementById('modalProductName').textContent = currentProduct.name;
      document.getElementById('modalBasePrice').textContent = `Base Price: ₱${currentProduct.price}`;
      document.getElementById('modalImg').textContent = currentProduct.emoji;
      document.getElementById('modalQty').textContent = 1;
      document.getElementById('specialInstructions').value = '';
      document.getElementById('modalStockLine').innerHTML = `
    <span class="stock-badge ${st.cls}" style="flex-shrink:0;">${st.label}</span>
    <div class="modal-stock-bar-track">
      <div class="modal-stock-bar-fill" style="width:${barPct}%;background:${st.barColor};"></div>
    </div>`;
      document.getElementById('addonsList').innerHTML = ADDONS.map(a => `
    <div class="addon-row">
      <input type="checkbox" id="ao_${a.id}" value="${a.id}" onchange="toggleAddon('${a.id}')">
      <label for="ao_${a.id}">${a.label}</label>
      <span class="addon-price">(+₱${a.price})</span>
    </div>`).join('');
      openModal('addModal');
    }

    function toggleAddon(id) {
      selectedAddons = selectedAddons.includes(id) ?
        selectedAddons.filter(x => x !== id) : [...selectedAddons, id];
    }

    function changeQty(delta) {
      modalQty = Math.max(1, modalQty + delta);
      document.getElementById('modalQty').textContent = modalQty;
    }

    function confirmAdd() {
      if (!currentProduct) return;
      const addons = selectedAddons.map(id => ADDONS.find(a => a.id === id));
      const addonTotal = addons.reduce((s, a) => s + a.price, 0);
      const note = document.getElementById('specialInstructions').value.trim();
      const inCart = cart.filter(i => i.id == currentProduct.id).reduce((s, i) => s + i.qty, 0);
      if (inCart + modalQty > currentProduct.stock) {
        showToast(`⚠️ Only ${currentProduct.stock} in stock (${inCart} already in order).`);
        return;
      }
      const existing = cart.find(i =>
        i.id == currentProduct.id &&
        JSON.stringify(i.addons.map(a => a.id).sort()) === JSON.stringify(selectedAddons.slice().sort()) &&
        i.note === note
      );
      if (existing) {
        existing.qty += modalQty;
      } else {
        cart.push({
          cartId: Date.now(),
          id: currentProduct.id,
          name: currentProduct.name,
          price: currentProduct.price,
          addons,
          addonTotal,
          qty: modalQty,
          note,
        });
      }
      closeModal('addModal');
      renderCart();
      showToast(`${currentProduct.name} added to order!`);
    }

    // ─── CART ─────────────────────────────────────────────
    function renderCart() {
      const container = document.getElementById('orderItems');
      const empty = document.getElementById('emptyOrder');
      document.getElementById('orderCount').textContent = cart.reduce((s, i) => s + i.qty, 0);
      if (!cart.length) {
        container.innerHTML = '';
        container.appendChild(empty);
        empty.style.display = 'flex';
        updateSummary();
        return;
      }
      empty.style.display = 'none';
      container.innerHTML = cart.map(item => {
        const lineTotal = (item.price + item.addonTotal) * item.qty;
        const addonsText = item.addons.length ?
          'Sub-' + item.addons.map(a => a.label.toLowerCase()).join(', ') :
          '';
        return `
      <div class="order-item">
        <div class="order-item-top">
          <div>
            <div class="order-item-name">${item.name}</div>
            ${addonsText ? `<div class="order-item-addons">${addonsText}</div>` : ''}
            ${item.note  ? `<div class="order-item-note">"${item.note}"</div>` : ''}
          </div>
          <div style="display:flex;align-items:center;gap:10px;margin-top:2px;">
            <div class="order-item-qty">
              <button class="qty-btn" onclick="adjustQty(${item.cartId},-1)">−</button>
              ${item.qty}
              <button class="qty-btn" onclick="adjustQty(${item.cartId},1)">+</button>
            </div>
            <div class="order-item-price">₱${lineTotal}</div>
          </div>
        </div>
        ${item.addonTotal ? `<div class="order-item-addons" style="text-align:right;color:var(--muted)">+₱${item.addonTotal * item.qty} add-ons</div>` : ''}
        <button class="remove-item" onclick="removeItem(${item.cartId})">✕ Remove</button>
      </div>`;
      }).join('');
      container.appendChild(empty);
      updateSummary();
    }

    function adjustQty(cartId, delta) {
      const item = cart.find(i => i.cartId === cartId);
      if (!item) return;
      item.qty = Math.max(1, item.qty + delta);
      renderCart();
    }

    function removeItem(cartId) {
      cart = cart.filter(i => i.cartId !== cartId);
      renderCart();
    }

    function clearAll() {
      if (!cart.length) return;
      cart = [];
      discount = {
        type: 'pct',
        value: 0
      };
      document.getElementById('discountRow').style.display = 'none';
      renderCart();
      showToast('Order cleared.');
    }

    function getSubtotal() {
      return cart.reduce((s, i) => s + (i.price + i.addonTotal) * i.qty, 0);
    }

    function getDiscountAmount(sub) {
      if (!discount.value) return 0;
      return discount.type === 'pct' ?
        Math.round(sub * discount.value / 100) :
        Math.min(discount.value, sub);
    }

    function updateSummary() {
      const sub = getSubtotal();
      const vat = vatOf(sub);
      const disc = getDiscountAmount(sub);
      document.getElementById('subtotalVal').textContent = fmtP(sub);
      document.getElementById('vatVal').textContent = fmtP(vat);
      document.getElementById('totalVal').textContent = fmtP(sub - disc);
      if (disc > 0) {
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountVal').textContent = `-${fmtP(disc)}`;
      } else {
        document.getElementById('discountRow').style.display = 'none';
      }
      buildReceipt();
    }

    // ─── BUILD RECEIPT ────────────────────────────────────
    function buildReceipt(cashPaid, custName, custContact, orderNum) {
      const paper = document.getElementById('receiptPaper');
      if (!cart.length && !cashPaid) {
        paper.innerHTML = '<div class="r-empty">Complete a checkout to<br>generate a receipt ☕</div>';
        return;
      }

      const sub = getSubtotal();
      const vat = vatOf(sub);
      const vatEx = sub - vat;
      const disc = getDiscountAmount(sub);
      const total = sub - disc;
      const now = new Date();
      const dStr = now.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
      });
      const tStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
      const oNum = orderNum || '(pending)';
      const cust = custName || '—';
      const cont = custContact || '—';
      const cash = cashPaid || 0;
      const change = cash > 0 ? cash - total : 0;
      const cashierName = '<?= htmlspecialchars($cashier_name) ?>';

      const itemsHTML = cart.map(item => {
        const lineTotal = ((item.price + (item.addonTotal || 0)) * item.qty).toFixed(2);
        const addonsHTML = (item.addons && item.addons.length) ?
          item.addons.map(a => `
          <div class="r-row">
            <span class="r-indent">+ ${a.label}</span>
            <span>x${item.qty}</span>
          </div>`).join('') :
          '';
        const noteHTML = item.note ?
          `<div class="r-indent r-muted"><i>"${item.note}"</i></div>` :
          '';
        return `
      <div><b>${item.name}</b></div>
      ${addonsHTML}
      ${noteHTML}
      <div class="r-row">
        <span>Qty: ${item.qty}</span>
        <span>₱${lineTotal}</span>
      </div>`;
      }).join('<div style="height:3px;border-top:1px dotted #eee;margin:4px 0;"></div>');

      const discHTML = disc > 0 ? `
    <div class="r-row">
      <span>Discount (${discount.type === 'pct' ? discount.value + '%' : 'Fixed'}):</span>
      <span>-₱${disc.toFixed(2)}</span>
    </div>` : '';

      const cashHTML = cash > 0 ? `
    <div class="r-line"></div>
    <div class="r-row"><span>Cash:</span><span>₱${cash.toFixed(2)}</span></div>
    <div class="r-row"><span>Change:</span><span>₱${change.toFixed(2)}</span></div>` : '';

      paper.innerHTML = `
    <div class="r-center r-bold" style="font-size:13px;letter-spacing:.05em;">☕ FiveSix Legazpi Cafe</div>
    <div class="r-center r-muted">123 Mabini St., Legazpi City</div>
    <div class="r-center r-muted">VAT Reg TIN: 123-456-789-000</div>
    <div class="r-line"></div>
    <div class="r-row"><span>Order #:</span><span class="r-bold">${oNum}</span></div>
    <div class="r-row"><span>Date:</span><span>${dStr}</span></div>
    <div class="r-row"><span>Time:</span><span>${tStr}</span></div>
    <div class="r-row"><span>Cashier:</span><span>${cashierName}</span></div>
    <div class="r-line"></div>
    <div class="r-row"><span>Customer:</span><span>${cust}</span></div>
    <div class="r-row"><span>Contact:</span><span>${cont}</span></div>
    <div class="r-dline"></div>
    <div class="r-center r-bold" style="margin-bottom:4px;">ITEMS ORDERED</div>
    <div class="r-dline"></div>
    ${itemsHTML}
    <div class="r-line"></div>
    <div class="r-row"><span>Subtotal:</span><span>₱${sub.toFixed(2)}</span></div>
    ${discHTML}
    <div class="r-dline"></div>
    <div class="r-rowtotal"><span>TOTAL:</span><span>₱${total.toFixed(2)}</span></div>
    <div class="r-dline"></div>
    <div class="r-center r-muted" style="margin:4px 0;">— VAT BREAKDOWN (VAT-inclusive) —</div>
    <div class="r-row r-muted"><span>VAT-able Amount (excl.):</span><span>₱${vatEx.toFixed(2)}</span></div>
    <div class="r-row r-muted"><span>VAT Amount (12%):</span><span>₱${vat.toFixed(2)}</span></div>
    ${cashHTML}
    <div class="r-line"></div>
    <div class="r-center r-muted" style="margin-top:4px;">This serves as your official receipt.</div>
    <div class="r-center">Thank you for visiting!</div>
    <div class="r-center">Come back soon ☕</div>
    <div class="r-center r-muted" style="margin-top:6px;">— Powered by FiveSix POS —</div>
  `;
    }

    // ─── PRINT RECEIPT ────────────────────────────────────
    function printReceipt() {
      const content = document.getElementById('receiptPaper').innerHTML;
      if (!content || content.includes('r-empty') || content.includes('Complete a checkout')) {
        showToast('⚠️ Complete a checkout first before printing.');
        return;
      }
      const win = window.open('', '_blank', 'width=320,height=700');
      win.document.write(`<!DOCTYPE html><html><head>
    <style>
      @page { size: 58mm auto; margin: 0; }
      body {
        font-family: 'Courier New', monospace;
        font-size: 10.5px;
        line-height: 1.6;
        width: 58mm;
        margin: 0;
        padding: 10px 8px;
      }
      .r-center  { text-align: center; }
      .r-bold    { font-weight: bold; }
      .r-line    { border-top: 1px dashed #bbb; margin: 5px 0; }
      .r-dline   { border-top: 2px solid #444; margin: 5px 0; }
      .r-row     { display: flex; justify-content: space-between; }
      .r-rowtotal{ display: flex; justify-content: space-between; font-weight: bold; font-size: 12px; }
      .r-indent  { padding-left: 10px; }
      .r-muted   { color: #888; font-size: 10px; }
      .r-empty   { display: none; }
    </style>
  </head><body>${content}</body></html>`);
      win.document.close();
      win.focus();
      setTimeout(() => {
        win.print();
        win.close();
      }, 300);
    }

    // ─── DISCOUNT ─────────────────────────────────────────
    function openDiscount() {
      openModal('discountModal');
      previewDiscount();
    }

    function setDiscType(type) {
      discType = type;
      document.getElementById('discPctBtn').classList.toggle('active', type === 'pct');
      document.getElementById('discFixBtn').classList.toggle('active', type === 'fix');
      document.getElementById('discUnit').textContent = type === 'pct' ? '%' : '₱';
      previewDiscount();
    }

    function previewDiscount() {
      const val = parseFloat(document.getElementById('discountInput').value) || 0;
      const sub = getSubtotal();
      document.getElementById('discPreview').textContent =
        `₱${discType === 'pct' ? Math.round(sub * val / 100) : Math.min(val, sub)}`;
    }

    function applyDiscount() {
      discount = {
        type: discType,
        value: parseFloat(document.getElementById('discountInput').value) || 0
      };
      updateSummary();
      closeModal('discountModal');
      showToast('Discount applied!');
    }

    // ─── CHECKOUT ─────────────────────────────────────────
    function openCheckout() {
      if (!cart.length) {
        showToast('Add items to order first!');
        return;
      }
      const sub = getSubtotal();
      const disc = getDiscountAmount(sub);
      document.getElementById('checkoutTotal').textContent = `₱${sub - disc}`;
      document.getElementById('cashInput').value = '';
      document.getElementById('changeDisplay').textContent = '';
      ['custFirst', 'custLast', 'custAddress', 'custContact'].forEach(id =>
        document.getElementById(id).value = ''
      );
      document.getElementById('checkoutTableBody').innerHTML = cart.map((item, i) => {
        const amt = (item.price + item.addonTotal) * item.qty;
        return `<tr>
      <td>${i + 1}</td>
      <td>${item.name}${item.addons.length ? ' (+addons)' : ''}</td>
      <td>${item.qty}</td>
      <td>₱${amt}</td>
    </tr>`;
      }).join('');
      openModal('checkoutModal');
    }

    function calcChange() {
      const sub = getSubtotal();
      const disc = getDiscountAmount(sub);
      const total = sub - disc;
      const cash = parseFloat(document.getElementById('cashInput').value) || 0;
      const change = cash - total;
      const el = document.getElementById('changeDisplay');
      if (cash > 0) {
        el.textContent = change >= 0 ?
          `CHANGE: ₱${change.toFixed(2)}` :
          `SHORT: ₱${Math.abs(change).toFixed(2)}`;
        el.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
      } else {
        el.textContent = '';
      }
    }

    // ─── CONFIRM CHECKOUT → POST to PHP ───────────────────
    async function confirmCheckout() {
      const cash = parseFloat(document.getElementById('cashInput').value) || 0;
      const sub = getSubtotal();
      const disc = getDiscountAmount(sub);
      const total = sub - disc;
      if (cash < total) {
        showToast('Insufficient cash amount!');
        return;
      }

      const payload = new URLSearchParams({
        action: 'checkout',
        items: JSON.stringify(cart),
        discount: JSON.stringify(discount),
        cash_tendered: cash,
        customer: JSON.stringify({
          first: document.getElementById('custFirst').value,
          last: document.getElementById('custLast').value,
          contact: document.getElementById('custContact').value,
          address: document.getElementById('custAddress').value,
        }),
      });

      try {
        const res = await fetch('Cashier.php', {
          method: 'POST',
          body: payload
        });
        const data = await res.json();

        if (data.success) {
          // Collect customer info before clearing
          const custName = [
            document.getElementById('custFirst').value,
            document.getElementById('custLast').value
          ].filter(Boolean).join(' ');
          const custContact = document.getElementById('custContact').value;

          // Build final receipt BEFORE clearing cart
          buildReceipt(cash, custName, custContact, data.order_ref);

          // Save receipt HTML so it survives cart reset
          const savedReceipt = document.getElementById('receiptPaper').innerHTML;

          closeModal('checkoutModal');
          cart = [];
          discount = {
            type: 'pct',
            value: 0
          };
          document.getElementById('discountRow').style.display = 'none';
          renderCart();

          // Refresh stock from DB
          try {
            const refresh = await fetch('Cashier.php', {
              method: 'POST',
              body: new URLSearchParams({
                action: 'get_products'
              })
            });
            const refreshData = await refresh.json();
            if (refreshData.success) PRODUCTS = refreshData.products;
          } catch (e) {
            console.log('Stock refresh failed, continuing anyway.');
          }
          renderProducts();

          // Switch tab first (buildReceipt fires but cart is empty — that's ok)
          switchTab('receipt');
          
          // Then restore the saved receipt AFTER, so it overwrites the empty state
          document.getElementById('receiptPaper').innerHTML = savedReceipt;
          showToast(`✓ ${data.message} Change: ₱${data.change.toFixed(2)}`);

        } else {
          showToast(`❌ ${data.message}`);
        }
      } catch (err) {
        showToast('❌ Network error. Please try again.');
        console.error(err);
      }
    }

    // ─── MODAL HELPERS ────────────────────────────────────
    function openModal(id) {
      document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }
    document.querySelectorAll('.modal-overlay').forEach(el => {
      el.addEventListener('click', e => {
        if (e.target === el) el.classList.remove('open');
      });
    });

    // ─── TOAST ────────────────────────────────────────────
    let toastTimer;

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
    }
  </script>
</body>


</html>