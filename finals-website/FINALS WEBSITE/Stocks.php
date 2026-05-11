<?php
require_once 'databasetoSQL.php';

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'restock') {

    header('Content-Type: application/json');

    $id  = intval($_POST['id']);
    $qty = floatval($_POST['qty']);

    $query = "UPDATE ingredients 
              SET current_quantity = current_quantity + $qty 
              WHERE ingredient_id = $id";

    $result = $conn->query($query);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => $conn->error,
            'query' => $query
        ]);
        exit;
    }

    echo json_encode([
        'success' => true
    ]);
    exit;
}
 
  if ($action === 'reduce') {
    $id  = intval($_POST['id']);
    $qty = floatval($_POST['qty']);
    $result = $conn->query("UPDATE ingredients SET current_quantity = current_quantity - $qty WHERE ingredient_id = $id");
    echo json_encode(['success' => (bool)$result]);
    exit;
  }
 
  if ($action === 'edit') {
    $id       = intval($_POST['id']);
    $name     = $conn->real_escape_string($_POST['name']);
    $qty  = floatval($_POST['qty']);

    $result = $conn->query("UPDATE ingredients SET ingredient_name='$name', current_quantity=$qty WHERE ingredient_id=$id");
    echo json_encode(['success' => (bool)$result]);
    exit;
  }
 
  if ($action === 'add') {
    $name     = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $unit     = $conn->real_escape_string($_POST['unit']);
    $qty      = floatval($_POST['qty']);
    $result   = $conn->query("INSERT INTO ingredients (ingredient_name, category, unit_of_measure, current_quantity) VALUES ('$name', '$category', '$unit', $qty)");
    echo json_encode(['success' => (bool)$result, 'id' => $conn->insert_id]);
    exit;
  }
 
  if ($action === 'delete') {
    $id = intval($_POST['id']);
    $result = $conn->query("DELETE FROM ingredients WHERE ingredient_id = $id");
    echo json_encode(['success' => (bool)$result]);
    exit;
  }
}

$ing_result = $conn->query("SELECT ingredient_id AS id, ingredient_name AS name, category, unit_of_measure AS unit, current_quantity AS qty FROM ingredients ORDER BY category, ingredient_name");
$ingredients_php = [];
while ($row = $ing_result->fetch_assoc()) {
  $row['qty'] = floatval($row['qty']);
  $ingredients_php[] = $row;
}
$ingredients_json = json_encode($ingredients_php);

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FiveSix Legazpi Cafe — Stocks</title>
  <link href="Stocks.css" type="text/css" rel="stylesheet">
  
</head>

<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="avatar">👤</div>
    <div class="sidebar-name">Dingdong Khan</div>
    <div class="sidebar-role">Administrator</div>
    <nav class="nav-links">
      <a class="nav-link" href="Merchandise.php"><span class="nav-icon">🖥</span> Dashboard</a>
      <a class="nav-link" href="OrderingSystem.php"><span class="nav-icon">📋</span> Orders</a>
      <a class="nav-link" href="Cashier.php"><span class="nav-icon">📊</span> Sales</a>
      <a class="nav-link active" href="Stocks.php"><span class="nav-icon">📦</span> Stocks</a>
      <a class="nav-link" href="Profile.html"><span class="nav-icon">👤</span> Profile</a>
    </nav>
    <div class="sidebar-spacer"></div>
    <button class="logout-btn" href="LOGIN.php" showToast('Logged out!')">Log out ➜</button>
  </aside>

  <!-- CONTENT -->
  <div class="content">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="brand">
        <div class="brand-icon">☕</div>
        <span class="brand-name">FiveSix Legazpi Cafe</span>
      </div>
      <div class="search-bar">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Search products..." oninput="applySearch()">
      </div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-time" id="liveTime"></div>
      </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-title">📦 Stock Management</div>
      <div class="page-sub">Monitor inventory levels, restock items, and manage product availability.</div>
    </div>

    <!-- KPI ROW -->
    <div class="kpi-row" id="kpiRow"></div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <span style="font-size:12px;font-weight:600;color:var(--muted);">Filter:</span>
      <button class="filter-chip active" onclick="setFilter('all',this)">All</button>
      <button class="filter-chip chip-ok" onclick="setFilter('ok',this)" style="background:var(--green-bg);color:var(--green);border-color:#9dd4b4;">In Stock</button>
      <button class="filter-chip chip-low" onclick="setFilter('low',this)">Low</button>
      <button class="filter-chip chip-crit" onclick="setFilter('crit',this)">Critical</button>
      <button class="filter-chip chip-out" onclick="setFilter('out',this)">Out of Stock</button>
      <div class="toolbar-right">
        <button class="add-stock-btn" onclick="openAddProduct()">＋ Add Product</button>
      </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
      <table class="stock-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Stock Level</th>
            <th class="center">Status</th>
            <th class="center">Actions</th>
          </tr>
        </thead>
        <tbody id="stockTableBody"></tbody>
      </table>
    </div>
  </div>

  <!-- RESTOCK MODAL -->
  <div class="modal-overlay" id="restockModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('restockModal')">✕</button>
      <div class="modal-title">Restock Item</div>
      <div class="modal-product-info" id="restockProductInfo"></div>
      <div class="modal-field">
        <label>Current Stock</label>
        <input type="number" id="currentStockDisplay" disabled>
      </div>
      <div class="modal-field">
        <label>Add Quantity</label>
        <input type="number" id="restockQty" placeholder="Enter amount to add..." min="1">
      </div>
      <div class="modal-actions">
        <button class="modal-cancel" onclick="closeModal('restockModal')">Cancel</button>
        <button class="modal-save" onclick="confirmRestock()">Confirm Restock</button>
      </div>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal-overlay" id="editModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
      <div class="modal-title">remove </div>
      <input type="hidden" id="editProductId">
      <div class="modal-field">
        <label>Product Name</label>
        <input type="text" id="editName" placeholder="Product name...">
      </div>
      <div class="modal-field">
        <label>Stock Quantity</label>
        <input type="number" id="editStock" placeholder="0" min="0">
      </div>
      <div class="modal-actions">
        <button class="modal-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button class="modal-save" onclick="confirmEdit()">Save Changes</button>
      </div>
    </div>
  </div>

  <!-- ADD PRODUCT MODAL -->
  <div class="modal-overlay" id="addProductModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('addProductModal')">✕</button>
      <div class="modal-title">Add New Product</div>
      <div class="modal-field">
        <label>Product Name</label>
        <input type="text" id="newName" placeholder="e.g. Cold Brew...">
      </div>
      <div class="modal-field">
        <label>Variant / Notes</label>
        <input type="text" id="newVariant" placeholder="e.g. Espresso, Classic...">
      </div>
      <div class="modal-field">
        <label>Category</label>
        <select id="newCategory">
          <option value="Espresso Based">Espresso Based</option>
          <option value="Non-Caffeine">Non-Caffeine</option>
          <option value="ADD-ONS">ADD-ONS</option>
        </select>
      </div>
      <div class="modal-field">
        <label>Price (₱)</label>
        <input type="number" id="newPrice" placeholder="0" min="0">
      </div>
      <div class="modal-field">
        <label>Initial Stock</label>
        <input type="number" id="newStock" placeholder="0" min="0">
      </div>
      <div class="modal-actions">
        <button class="modal-cancel" onclick="closeModal('addProductModal')">Cancel</button>
        <button class="modal-save" onclick="confirmAddProduct()">Add Product</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    let products = <?= $ingredients_json ?>; //GET ALL DATA FROM PHP SIR

    let currentFilter = 'all';
    let searchQuery = '';
    let selectedProductId = null;

    // ─── CLOCK ───────────────────────────────────────────────────────
    function updateClock() {
      const now = new Date();
      document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
      });
      document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── STOCK HELPERS ───────────────────────────────────────────────
    function getStockLevel(stock) {
      if (stock === 0) return 'out';
      if (stock <= 5) return 'crit';
      if (stock <= 10) return 'low';
      return 'ok';
    }

    function getStockBadge(stock) {
      const lvl = getStockLevel(stock);
      const map = {
        ok: {
          cls: 'badge-ok',
          label: 'In Stock'
        },
        low: {
          cls: 'badge-low',
          label: 'Low Stock'
        },
        crit: {
          cls: 'badge-crit',
          label: 'Critical'
        },
        out: {
          cls: 'badge-out',
          label: 'Out of Stock'
        },
      };
      return `<span class="stock-badge ${map[lvl].cls}">${map[lvl].label}</span>`;
    }

    function getBarColor(stock) {
      const lvl = getStockLevel(stock);
      return {
        ok: '#3a7d54',
        low: '#b07a10',
        crit: '#b04040',
        out: '#bbb'
      } [lvl];
    }

    // ─── FILTER / SEARCH ─────────────────────────────────────────────
    function setFilter(f, btn) {
      currentFilter = f;
      document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderTable();
    }

    function applySearch() {
      searchQuery = document.getElementById('searchInput').value.toLowerCase();
      renderTable();
    }

    function getFiltered() {
      return products.filter(p => {
        const lvl = getStockLevel(p.qty);
        const matchFilter = currentFilter === 'all' || lvl === currentFilter;
        const matchSearch = !searchQuery || p.name.toLowerCase().includes(searchQuery) || p.category.toLowerCase().includes(searchQuery);
        return matchFilter && matchSearch;
      });
    }

    // ─── KPI ─────────────────────────────────────────────────────────
    function renderKPI() {
      const total = products.length;
      const out = products.filter(p => p.qty
 === 0).length;
      const crit = products.filter(p => p.qty
 > 0 && p.qty
 <= 5).length;
      const ok = products.filter(p => p.qty
 > 10).length;
      document.getElementById('kpiRow').innerHTML = `
    <div class="kpi-card">
      <div class="kpi-label">Total Products</div>
      <div class="kpi-value">${total}</div>
      <div class="kpi-sub">across all categories</div>
    </div>
    <div class="kpi-card ok">
      <div class="kpi-label">In Stock</div>
      <div class="kpi-value">${ok}</div>
      <div class="kpi-sub">stock &gt; 10 units</div>
    </div>
    <div class="kpi-card warn">
      <div class="kpi-label">Low / Critical</div>
      <div class="kpi-value">${crit + products.filter(p => p.qty
 > 5 && p.qty
 <= 10).length}</div>
      <div class="kpi-sub">need restocking soon</div>
    </div>
    <div class="kpi-card alert">
      <div class="kpi-label">Out of Stock</div>
      <div class="kpi-value">${out}</div>
      <div class="kpi-sub">unavailable to sell</div>
    </div>
  `;
    }

    // ─── TABLE ───────────────────────────────────────────────────────
    function renderTable() {
      const filtered = getFiltered();
      const tbody = document.getElementById('stockTableBody');
      if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);font-size:14px;">No products found.</td></tr>`;
        return;
      }
      tbody.innerHTML = filtered.map(p => {
        const maxRef = Math.max(p.qty
, 30);
        const barPct = p.qty
 === 0 ? 0 : Math.round((p.qty
 / maxRef) * 100);
        const barColor = getBarColor(p.qty
);
        return `
      <tr>
        <td style="color:var(--muted);font-size:12.5px;">${p.name}</td>
        <td style="color:var(--muted);font-size:12.5px;">${p.category}</td>
        <td style="font-weight:600;color:var(--accent);">${p.qty}${p.unit}</td>
        <td>
          <div class="stock-bar-wrap">
            <div class="stock-bar-track">
              <div class="stock-bar-fill" style="width:${barPct}%;background:${barColor};"></div>
            </div>
            <span class="stock-num" style="color:${barColor};">${p.qty}</span>
          </div>
        </td>
        <td class="center">${getStockBadge(p.qty)}</td>
        <td class="center">
          <div class="action-row" style="justify-content:center;">
            <button class="tbl-btn" onclick="openRestock(${p.id})">＋ Restock</button>
            <button class="tbl-btn" onclick="openEdit(${p.id})">✎ Edit</button>
            <button class="tbl-btn danger" onclick="deleteProduct(${p.id})">✕</button>
          </div>
        </td>
      </tr>
    `;
      }).join('');
    }

    function render() {
      renderKPI();
      renderTable();
    }

    // ─── RESTOCK ─────────────────────────────────────────────────────
    function openRestock(id) {
      const p = products.find(x => x.id == id);
      if (!p) return;
      selectedProductId = id;
      document.getElementById('restockProductInfo').innerHTML = `
    <div class="modal-product-emoji">${p.emoji}</div>
    <div>
      <div class="modal-product-name">${p.name}</div>
      <div class="modal-product-cat">${p.category}</div>
    </div>
  `;
      document.getElementById('currentStockDisplay').value = p.qty;
      document.getElementById('restockQty').value = '';
      openModal('restockModal');
    }

    function confirmRestock() {
      const p = products.find(x => x.id == selectedProductId);
      if (!p) return;
      const qty = parseInt(document.getElementById('restockQty').value) || 0;
      if (qty <= 0) {
        showToast('Please enter a valid quantity.');
        return;
      }
      fetch('Stocks.php', {
          method: 'POST',
          body: new URLSearchParams({
            action: 'restock',
            id: p.id,
            qty: qty
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            p.qty
 += qty;
            closeModal('restockModal');
            render();
            showToast(`✓ ${p.name} restocked by ${qty}. New stock: ${p.qty
}`);
          } else {
            showToast('Failed to restock. Try again.');
          }
        });
    }


    // ─── EDIT ─────────────────────────────────────────────────────────
    function openEdit(id) {
      const p = products.find(x => x.id == id);
      if (!p) return;
      selectedProductId = id;
      document.getElementById('editProductId').value = id;
      document.getElementById('editName').value = p.name;
      document.getElementById('editStock').value = p.qty;
      openModal('editModal');
    }

    function confirmEdit() {

      const p = products.find(x => x.id == selectedProductId);


      if (!p) return;

      const name = document.getElementById('editName').value.trim() || p.name;

      const qty = parseInt(document.getElementById('editStock').value) || 0;

      if (qty > p.qty) {
        showToast('input greater than current stock');
        return;
      };

      fetch('Stocks.php', {

        method: 'POST',

        body: new URLSearchParams({
          action: 'edit',
          id: p.id,
          name,
          qty
        })

      })

      .then(res => res.json())

      .then(data => {

        if (data.success) {

          p.name = name;
          p.qty = qty;

          closeModal('editModal');

          render();

          showToast(`✓ ${p.name} updated successfully.`);

        } else {
          showToast('Failed to update.');
        }
      });
    }

    // ─── ADD PRODUCT ─────────────────────────────────────────────────
    function openAddProduct() {
      openModal('addProductModal');
    }

  function confirmAddProduct() {
  const name    = document.getElementById('newName').value.trim();
  const variant = document.getElementById('newVariant').value.trim() || '—';
  const cat     = document.getElementById('newCategory').value;
  const price   = parseInt(document.getElementById('newPrice').value) || 0;
  const stock   = parseInt(document.getElementById('newStock').value) || 0;
  if (!name) { showToast('Product name is required.'); return; }

  fetch('Stocks.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'add', name, variant, category: cat, price, stock })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const emojis = { 'Espresso Based': '☕', 'Non-Caffeine': '🍵', 'ADD-ONS': '🧃' };
      products.push({ id: data.id, emoji: emojis[cat] || '☕', name, variant, price, category: cat, stock });
      closeModal('addProductModal');
      document.getElementById('newName').value = '';
      document.getElementById('newVariant').value = '';
      document.getElementById('newPrice').value = '';
      document.getElementById('newStock').value = '';
      render();
      showToast(`✓ ${name} added to inventory.`);
    } else {
      showToast('Failed to add product. Try again.');
    }
  });
}

    // ─── DELETE ──────────────────────────────────────────────────────
    function deleteProduct(id) {
  const p = products.find(x => x.id == id);
  if (!p) return;
  if (!confirm(`Remove "${p.name}" from inventory?`)) return;

  fetch('Stocks.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'delete', id: id })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      products = products.filter(x => x.id !== id);
      render();
      showToast(`${p.name} removed from inventory.`);
    } else {
      showToast('Failed to delete. Try again.');
    }
  });
}

    // ─── MODAL HELPERS ───────────────────────────────────────────────
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

    // ─── TOAST ───────────────────────────────────────────────────────
    let toastTimer;

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
    }

    render();
  </script>
</body>

</html>