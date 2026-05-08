const ctx = document.getElementById("myChart").getContext("2d");
let chartInstance = null;
let globalChartData = [];

//fetch("script.php")
//.then((response) => response.json())
// .then((data) => {
//   globalChartData = data;
//  createChart(data, "bar");
// });
globalChartData = [
  { date: "2025-11-01", income: 1500 },
  { date: "2025-11-02", income: 2300 },
  { date: "2025-11-03", income: 1800 },
  { date: "2025-11-04", income: 3200 },
  { date: "2025-11-05", income: 2700 },
];
createChart(globalChartData, "bar");

function createChart(chartData, type) {
  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(ctx, {
    type: type,
    data: {
      labels: chartData.map((row) => row.date),
      datasets: [
        {
          label: "Income",
          data: chartData.map((row) => row.income),
          backgroundColor: "rgba(99, 102, 241, 0.2)",
          borderColor: "rgba(99, 102, 241, 1)",
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      scales: { y: { beginAtZero: true } },
    },
  });
}

let reportChartInstance = null;

function openReport() {
  const modal = document.getElementById("reportModal");
  modal.style.display = "flex";

  const tbody = document.getElementById("reportTableBody");
  tbody.innerHTML = "";
  let total = 0;

  globalChartData.forEach((row) => {
    total += parseFloat(row.income);
    tbody.innerHTML += `
            <tr style="border-bottom: 1px solid var(--border, #eee);">
              <td style="padding: 8px 0;">${row.date}</td>
              <td style="text-align: right; padding: 8px 0;">₱${parseFloat(row.income).toLocaleString("en-PH", { minimumFractionDigits: 2 })}</td>
            </tr>`;
  });

  document.getElementById("reportTotal").textContent =
    "₱" + total.toLocaleString("en-PH", { minimumFractionDigits: 2 });

  if (reportChartInstance) reportChartInstance.destroy();
  const rCtx = document.getElementById("reportChart").getContext("2d");
  reportChartInstance = new Chart(rCtx, {
    type: "bar",
    data: {
      labels: globalChartData.map((row) => row.date),
      datasets: [
        {
          label: "Income",
          data: globalChartData.map((row) => row.income),
          backgroundColor: "rgba(99, 102, 241, 0.2)",
          borderColor: "rgba(99, 102, 241, 1)",
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      scales: { y: { beginAtZero: true } },
    },
  });
}

function closeReport() {
  document.getElementById("reportModal").style.display = "none";
  if (reportChartInstance) reportChartInstance.destroy();
}

document.getElementById("reportModal").addEventListener("click", function (e) {
  if (e.target === this) closeReport();
});

// --- your existing sorting + search code stays below ---
