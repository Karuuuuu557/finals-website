<?php
$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "main";
$username = "avnadmin";
$password = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
} 

$query = "SELECT ingredient_id, ingredient_name, category, unit_of_measure AS units, current_quantity AS qty FROM ingredients WHERE is_active = 1";
$result = mysqli_query($conn, $query);

//wag remove comments muna sa part sa baba
// ===== FETCH DAILY DATA FOR CHART =====
// ===== FETCH DAILY DATA FOR CHART =====
$dailyQuery = "
    SELECT 
        DATE(created_at) as date,
        DATE_FORMAT(created_at, '%Y-%m-%d') as day_label,
        COUNT(*) as total_transactions,
        COALESCE(SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(detail, 'Cash:', 1),'Total: ₱',-1) AS DECIMAL(10,2))), 0) as total_revenue
    FROM activity_logs
    WHERE type = 'sale'
    GROUP BY DATE(created_at), DATE_FORMAT(created_at, '%Y-%m-%d')
    ORDER BY DATE(created_at) DESC
    LIMIT 30
";
$dailyResult = mysqli_query($conn, $dailyQuery);

$dailyLabels = [];
$dailyRevenue = [];
$dailyTransactions = [];

if ($dailyResult && mysqli_num_rows($dailyResult) > 0) {
    while ($row = mysqli_fetch_assoc($dailyResult)) {
        $dailyLabels[] = $row['day_label'];
        $dailyRevenue[] = (float)($row['total_revenue'] ?? 0);
        $dailyTransactions[] = (int)$row['total_transactions'];
    }
    // Reverse to show oldest first
    $dailyLabels = array_reverse($dailyLabels);
    $dailyRevenue = array_reverse($dailyRevenue);
    $dailyTransactions = array_reverse($dailyTransactions);
}

// ===== FETCH MONTHLY DATA FOR CHART =====
// ===== FETCH MONTHLY DATA FOR CHART =====
$monthlyQuery = "
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month_label,
        YEAR(created_at) as year,
        MONTH(created_at) as month,
        COUNT(*) as total_transactions,
        COALESCE(SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(detail, 'Cash:', 1),'Total: ₱',-1) AS DECIMAL(10,2))), 0) as total_revenue
    FROM activity_logs
    WHERE type = 'sale'
    GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y')
    ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
";
$monthlyResult = mysqli_query($conn, $monthlyQuery);

$monthlyLabels = [];
$monthlyRevenue = [];
$monthlyTransactions = [];

if ($monthlyResult && mysqli_num_rows($monthlyResult) > 0) {
    while ($row = mysqli_fetch_assoc($monthlyResult)) {
        $monthlyLabels[] = $row['month_label'];
        $monthlyRevenue[] = (float)($row['total_revenue'] ?? 0);
        $monthlyTransactions[] = (int)$row['total_transactions'];
    }
}

// Convert to JSON for JavaScript
$dailyChartJSON = json_encode([
    'labels' => $dailyLabels,
    'revenue' => $dailyRevenue,
    'transactions' => $dailyTransactions
]);

$monthlyChartJSON = json_encode([
    'labels' => $monthlyLabels,
    'revenue' => $monthlyRevenue,
    'transactions' => $monthlyTransactions
]);

mysqli_close($conn);
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Owner Dashboard — Inventory Management</title>
    <link
      href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="GlobalStyles.css" />
    <link rel="stylesheet" href="Merchandise.css" />
    <script>
      // Clear any Chart.js cache
      if (window.Chart) {
        delete window.Chart;
      }
    </script>
  </head>

  <body data-theme="light">
    <div class="app">
      <!-- SIDEBAR -->
      <aside class="sidebar">
        <div class="brand">
          Inventory
          <span
            style="
              font-weight: 400;
              color: var(--muted);
              font-size: 20px;
              display: block;
            "
            >Owner Panel</span
          >
        </div>
        <div class="menu">
          <a href="Website.html">🏠 Home </a>
          <a class="nav-link" href="Merchandise.php"
            ><span class="nav-icon">🖥</span> Dashboard</a
          >
          <a class="nav-link active" href="OrderingSystem.php"
            ><span class="nav-icon">📋</span> Orders</a
          >
          <a class="nav-link" href="Cashier.php"
            ><span class="nav-icon">📊</span> Sales</a
          >
          <a class="nav-link" href="Stocks.php"
            ><span class="nav-icon">📦</span> Stocks</a
          >
          <a class="nav-link" href="Profile.html"
            ><span class="nav-icon">👤</span> Profile</a
          >
        </div>
      </aside>

      <div>
        <!-- TOPBAR -->
        <header class="topbar">
          <div style="display: flex; gap: 12px; align-items: center">
            <input class="search" placeholder="Search products" />
            <div style="font-size: 13px; color: var(--muted)">
              Last updated: <strong>Nov 27, 2025</strong>
            </div>
          </div>
          <div class="controls">
            <div style="text-align: right">
              <div style="font-size: 13px; color: var(--muted)">Owner</div>
              <div style="font-weight: 700">FiveSix Legazpi Cafe</div>
            </div>
            <div><img src="IMAGES/Logo.png" class="Logo" /></div>
          </div>
        </header>

        <!-- MAIN -->
        <main>
          <section class="grid-4 fade-in">
            <div class="card kpi">
              <div class="icon" style="background: #e8f4ff">💰</div>
              <div>
                <div class="value" style="color: var(--accent)">
                  ₱10,000 <span class="trend trend-up">↑ 8.2%</span>
                </div>
                <div class="label">Total Revenue (MTD)</div>
              </div>
            </div>

            <div class="card kpi">
              <div class="icon" style="background: #e8ffe8">📈</div>
              <div>
                <div class="value" style="color: var(--accent)">
                  ₱4,000 <span class="trend trend-up">↑ 4.4%</span>
                </div>
                <div class="label">Net Profit (MTD)</div>
              </div>
            </div>

            <div class="card kpi">
              <div class="icon" style="background: #fff2e6">💸</div>
              <div>
                <div class="value" style="color: var(--danger)">
                  ₱3,000 <span class="trend trend-down">↓ 1.2%</span>
                </div>
                <div class="label">Operating Cost</div>
              </div>
            </div>

            <div class="card kpi">
              <div class="icon" style="background: #eeeaff">🛒</div>
              <div>
                <div class="value">
                  320 <span class="trend trend-down">↓ 2.1%</span>
                </div>
                <div class="label">Purchases (MTD)</div>
              </div>
            </div>
          </section>

          <section class="two-col">
          <div class="card fade-in" style="min-height: 260px">
            <h3 style="margin: 0 0 12px 0">Sales Analytics</h3>
            
            <div style="display: flex; gap: 8px; margin-bottom: 12px;">
              <button class="btn" onclick="toggleView('daily')" id="btnDaily">📅 Daily (30 Days)</button>
              <button class="btn" onclick="toggleView('monthly')" id="btnMonthly">📊 Monthly (12 Months)</button>
            </div>
            
            <div style="position: relative; height: 300px;">
              <canvas id="myChart"></canvas>
            </div>
            
            <div style="margin-top: 12px; display: flex; gap: 12px; align-items: center;">
              <button class="btn" onclick="openReport()">View Report</button>
              <div style="color: var(--muted); font-size: 13px">
                Toggle between daily and monthly views
              </div>
            </div>
         </div>

        

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
          window.dailyData = <?php echo $dailyChartJSON; ?>;
          window.monthlyData = <?php echo $monthlyChartJSON; ?>;
        </script>
        <script src="script2.js"></script>
        <div id="reportModal" style="
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    ">
      <div style="
        background: var(--card, #fff);
        border-radius: 16px;
        padding: 28px;
        width: 90%;
        max-width: 700px;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
      ">
        <button onclick="closeReport()" style="
          position: absolute;
          top: 16px; right: 16px;
          background: none;
          border: none;
          font-size: 20px;
          cursor: pointer;
          color: var(--muted);
        ">✕</button>

        <h2 style="margin: 0 0 4px 0">📊 Sales Report</h2>
        <p style="color: var(--muted); font-size: 13px; margin: 0 0 20px 0">Summary of income by date</p>

        <canvas id="reportChart" height="120"></canvas>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
          <thead>
            <tr style="border-bottom: 2px solid var(--border, #eee);">
              <th style="text-align: left; padding: 8px 0;">Date</th>
              <th style="text-align: right; padding: 8px 0;">Income</th>
            </tr>
          </thead>
          <tbody id="reportTableBody"></tbody>
          <tfoot>
            <tr style="border-top: 2px solid var(--border, #eee); font-weight: 700;">
              <td style="padding: 10px 0;">Total</td>
              <td style="text-align: right; padding: 10px 0; color: var(--accent);" id="reportTotal"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
             
      </div>

            <aside class="card fade-in">
              <h3 style="margin: 0 0 12px 0">Alerts & Inventory Health</h3>
              <div style="display: flex; flex-direction: column; gap: 8px">
                <!--<div><span class="badge critical">CRITICAL</span> Coffee Beans — <strong>10</strong> left</div>
              <div><span class="badge low">LOW</span> Sugar Cubes — <strong>15</strong> left</div>
              <div><span class="badge low">LOW</span> Creamer — <strong>15</strong> left</div>
              <div><span class="badge ok">OK</span> Parle-G — <strong>120</strong> left</div>-->
              </div>
            </aside>
          </section>

          <section style="margin-top: 18px">
            <div class="card fade-in">
              <h3 style="margin: 0 0 12px 0">PRODUCT REPORTS</h3>
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                  margin-bottom: 12px;
                "
              >
                <div style="display: flex; gap: 8px; align-items: center">
                  <button class="btn" id="sortName">Sort Name</button>
                  <button class="btn" id="sortSold">Sort Stocks</button>
                </div>
                <div style="display: flex; gap: 8px; align-items: center">
                  <!--<div style="color:var(--muted);font-size:13px">Rows per page</div>
               <select id="rowsPerPage">
                  <option>5</option>
                  <option selected>10</option>
                  <option>25</option>
                </select>-->
                </div>
              </div>

              <table class="table">
                <thead>
                  <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Remaining</th>
                  </tr>
                </thead>
                <tbody id="productsTable">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                      <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="product-row" style="border-bottom: solid rgb(114, 110, 110)">
                          <td style="display: flex; gap: 10px; align-items: center">
                              <strong><?= htmlspecialchars($row['ingredient_name']) ?></strong>
                            </div>
                          </td>
                          <td>
                            <strong>
                                 <?= htmlspecialchars($row['category']) ?>
                            </strong>
                          </td>
                          <td>
                            <strong><span class="count"><?= (int)$row['qty'], $row['units'] ?></span></strong>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="4" style="text-align:center; color:var(--muted)">No products found.</td></tr>
                    <?php endif; ?>
                  </tbody>
              </table>

              <!-- <div class="table-footer">
              <div style="color:var(--muted)">Showing 1–3 of 3</div>
              <div class="pager">
                <button id="prev">Prev</button>
                <button id="next">Next</button>
              </div>
            </div>
          -->
            </div>
          </section>
        </main>
      </div>
    </div>

    <script
      src="https://kit.fontawesome.com/16140c4749.js"
      crossorigin="anonymous"
    ></script>

    <script>
      // sorting system

      let sortNameAsc = true;
      let sortSoldAsc = true;
      document
        .getElementById("sortSold")
        .addEventListener("click", function () {
          const tableBody = document.getElementById("productsTable");

          // Convert table rows to array
          let rows = Array.from(tableBody.querySelectorAll(".product-row"));

          rows.sort((a, b) => {
            const soldA = parseInt(a.children[1].textContent.trim());
            const soldB = parseInt(b.children[1].textContent.trim());

            if (sortSoldAsc) {
              return soldA - soldB; // Low → High
            } else {
              return soldB - soldA; // High → Low
            }
          });

          // Re-attach rows to table in sorted order
          rows.forEach((row) => tableBody.appendChild(row));

          // Flip the sorting direction
          sortSoldAsc = !sortSoldAsc;
        });

      document
        .getElementById("sortName")
        .addEventListener("click", function () {
          const tableBody = document.getElementById("productsTable");

          let rows = Array.from(tableBody.querySelectorAll(".product-row"));

          rows.sort((a, b) => {
            const nameA = a
              .querySelector("strong")
              .textContent.trim()
              .toLowerCase();
            const nameB = b
              .querySelector("strong")
              .textContent.trim()
              .toLowerCase();

            if (sortNameAsc) {
              return nameA.localeCompare(nameB);
            } else {
              return nameB.localeCompare(nameA);
            }
          });

          // Re-attach rows to table (sorted)
          rows.forEach((row) => tableBody.appendChild(row));

          // Flip sorting direction
          sortNameAsc = !sortNameAsc;
        });

      //------------

      // for alerts system
      function updateInventoryHealth() {
        const tableBody = document.getElementById("productsTable");
        const productRows = tableBody.querySelectorAll(".product-row");

        const alertContainer = document.querySelector(
          "aside.card.fade-in div:nth-child(2)",
        );

        alertContainer.innerHTML = "";

        // will go through each product row
        productRows.forEach((row) => {
          // for product name and remaining stock
          const productName = row.querySelector("strong").textContent;
          const remaining = parseInt(row.querySelector(".count").textContent);

          // Decide badge text and color
          let badgeText = "";
          let badgeClass = "";
          

        
        

            if (remaining <= 10) {
              badgeText = "CRITICAL";
              badgeClass = "critical";
            } else if (remaining <= 20) {
              badgeText = "LOW";
              badgeClass = "low";
            } else {
              badgeText = "OK";
              badgeClass = "ok";
            }
          

          // Create alert element
          const alertDiv = document.createElement("div");
          alertDiv.innerHTML = `<span class="badge ${badgeClass}">${badgeText}</span> ${productName} — <strong>${remaining}</strong> left`;

          // Add alert to the container
          alertContainer.appendChild(alertDiv);
        });
      }

      // Run initially
      updateInventoryHealth();

      // Interactive search
      const searchInput = document.querySelector(".search");

      searchInput.addEventListener("input", function () {
        const query = searchInput.value.toLowerCase();
        const productRows = document.querySelectorAll(".product-row");

        productRows.forEach((row) => {
          const productName = row
            .querySelector("td strong")
            .textContent.toLowerCase();
          const productCategoryDiv = row.querySelector("td strong + div"); // category under product name
          const productCategory = productCategoryDiv
            ? productCategoryDiv.textContent.toLowerCase()
            : "";

          if (productName.includes(query) || productCategory.includes(query)) {
            row.style.display = "";
          } else {
            row.style.display = "none";
          }
        });
      });
    </script>
  </body>
</html>