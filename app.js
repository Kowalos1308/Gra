const USERS = ["Klaudia", "Damian", "Wera"];

function stockImageUrl(query) {
  return `https://source.unsplash.com/600x600/?${encodeURIComponent(query)}`;
}

const DEVICES = [
  {
    id: "watermelon-ice-strawberry-red-bull-strawberry-kiwi",
    summaryEn: "Watermelon Ice/ Strawberry Red Bull/Strawberry Kiwi",
    pl: "Arbuz Ice / Truskawka Red Bull / Truskawka Kiwi",
    imageParts: [
      [stockImageUrl("watermelon fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("strawberry fruit"), stockImageUrl("energy drink can")],
      [stockImageUrl("strawberry fruit"), stockImageUrl("kiwi fruit")]
    ]
  },
  {
    id: "strawberry-watermelon-bluberry-on-ice-kiwi-lemon",
    summaryEn: "Strawberry Watermelon/Bluberry On Ice/Kiwi Lemon",
    pl: "Truskawka Arbuz / Borówka Ice / Kiwi Cytryna",
    imageParts: [
      [stockImageUrl("strawberry fruit"), stockImageUrl("watermelon fruit")],
      [stockImageUrl("blueberry fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("kiwi fruit"), stockImageUrl("lemon fruit")]
    ]
  },
  {
    id: "mixed-berries-peach-apple-ice-lady-killer",
    summaryEn: "Mixed Berries/Peach Apple Ice/Lady Killer",
    pl: "Leśne Owoce / Brzoskwinia Jabłko Ice / Lady Killer",
    imageParts: [
      [stockImageUrl("berries mix"), stockImageUrl("berries closeup")],
      [stockImageUrl("peach fruit"), stockImageUrl("apple with ice")],
      [stockImageUrl("cocktail drink"), stockImageUrl("tropical cocktail")]
    ]
  },
  {
    id: "mango-ice-banana-ice-blue-razz-lemoniade",
    summaryEn: "Mango Ice/Banana Ice/Blue Razz Lemoniade",
    pl: "Mango Ice / Banan Ice / Niebieska Malina Lemoniada",
    imageParts: [
      [stockImageUrl("mango fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("banana fruit"), stockImageUrl("ice cubes macro")],
      [stockImageUrl("blue raspberry candy"), stockImageUrl("lemonade drink")]
    ]
  },
  {
    id: "peach-mango-pineapple-strawberry-raspberry-ice-triple-mango",
    summaryEn: "Peach Mango Pineapple/Strawberry Raspberry Ice/Triple Mango",
    pl: "Brzoskwinia Mango Ananas / Truskawka Malina Ice / Potrójne Mango",
    imageParts: [
      [stockImageUrl("peach fruit"), stockImageUrl("mango pineapple")],
      [stockImageUrl("strawberry fruit"), stockImageUrl("raspberry with ice")],
      [stockImageUrl("mango slices"), stockImageUrl("mango fruit pile")]
    ]
  },
  {
    id: "strawberry-grape-peach-mango-ice-pop",
    summaryEn: "Strawberry Grape/Peach Mango/Ice Pop",
    pl: "Truskawka Winogrono / Brzoskwinia Mango / Ice Pop",
    imageParts: [
      [stockImageUrl("strawberry fruit"), stockImageUrl("grapes fruit")],
      [stockImageUrl("peach fruit"), stockImageUrl("mango fruit")],
      [stockImageUrl("ice pop"), stockImageUrl("popsicle")]
    ]
  },
  {
    id: "strawberry-ice-sour-apple-bluberry-raspberry",
    summaryEn: "Strawberry Ice/Sour Apple/Bluberry Raspberry",
    pl: "Truskawka Ice / Kwaśne Jabłko / Borówka Malina",
    imageParts: [
      [stockImageUrl("strawberry fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("green apple"), stockImageUrl("sour candy")],
      [stockImageUrl("blueberry fruit"), stockImageUrl("raspberry fruit")]
    ]
  },
  {
    id: "grape-ice-cherry-ice-blue-sour-raspberry",
    summaryEn: "Grape Ice/Cherry Ice/Blue Sour Raspberry",
    pl: "Winogrono Ice / Wiśnia Ice / Kwaśna Niebieska Malina",
    imageParts: [
      [stockImageUrl("grapes fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("cherry fruit"), stockImageUrl("ice cubes macro")],
      [stockImageUrl("blue raspberry"), stockImageUrl("sour candy blue")]
    ]
  },
  {
    id: "peach-ice-red-apple-bluberry-cherry-cranberry",
    summaryEn: "Peach Ice/Red Apple/Bluberry Cherry Cranberry",
    pl: "Brzoskwinia Ice / Czerwone Jabłko / Borówka Wiśnia Żurawina",
    imageParts: [
      [stockImageUrl("peach fruit"), stockImageUrl("ice cubes")],
      [stockImageUrl("red apple"), stockImageUrl("apple fruit closeup")],
      [stockImageUrl("blueberry fruit"), stockImageUrl("cherry cranberry")]
    ]
  },
  {
    id: "kiwi-passion-fruit-guava-green-apple-juicy-peach",
    summaryEn: "Kiwi Passion Fruit Guava/Green Apple/Juicy Peach",
    pl: "Kiwi Marakuja Guawa / Zielone Jabłko / Soczysta Brzoskwinia",
    imageParts: [
      [stockImageUrl("kiwi fruit"), stockImageUrl("passion fruit guava")],
      [stockImageUrl("green apple"), stockImageUrl("green apple macro")],
      [stockImageUrl("juicy peach"), stockImageUrl("peach slices")]
    ]
  }
];

const STORAGE_KEY = "vape-orders-v2";
const ADMIN_PASSWORD = "ogorek123";

function defaultOrders() {
  return Object.fromEntries(
    USERS.map((user) => [user, Object.fromEntries(DEVICES.map((device) => [device.id, 0]))])
  );
}

function loadOrders() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) return defaultOrders();

  try {
    const parsed = JSON.parse(raw);
    const base = defaultOrders();

    USERS.forEach((user) => {
      if (!parsed[user]) return;
      DEVICES.forEach((device) => {
        const value = Number(parsed[user][device.id]);
        base[user][device.id] = Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
      });
    });

    return base;
  } catch {
    return defaultOrders();
  }
}

function saveOrders(orders) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(orders));
}

function renderOrderingPage() {
  const userPicker = document.getElementById("userPicker");
  const flavorGrid = document.getElementById("flavorGrid");
  const activeUserLabel = document.getElementById("activeUserLabel");
  const adminForm = document.getElementById("adminAccessForm");
  const adminPassword = document.getElementById("adminPassword");
  const adminMessage = document.getElementById("adminAccessMessage");

  if (!userPicker || !flavorGrid || !activeUserLabel) return;

  const orders = loadOrders();
  let activeUser = USERS[0];

  function refreshUserButtons() {
    userPicker.querySelectorAll("button").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.user === activeUser);
    });
    activeUserLabel.textContent = `Aktywny: ${activeUser}`;
  }

  USERS.forEach((user) => {
    const btn = document.createElement("button");
    btn.className = "user-btn";
    btn.textContent = user;
    btn.dataset.user = user;
    btn.addEventListener("click", () => {
      activeUser = user;
      refreshUserButtons();
      renderDeviceCounts();
    });
    userPicker.appendChild(btn);
  });

  function renderDeviceCounts() {
    flavorGrid.querySelectorAll(".qty-value").forEach((node) => {
      const deviceId = node.dataset.device;
      node.textContent = orders[activeUser][deviceId];
    });
  }

  DEVICES.forEach((device) => {
    const card = document.createElement("article");
    card.className = "flavor-card";

    const collage = document.createElement("div");
    collage.className = "flavor-collage";

    device.imageParts.forEach((pair, partIndex) => {
      const segment = document.createElement("div");
      segment.className = "flavor-segment";

      const top = document.createElement("div");
      top.className = "segment-half";
      top.style.backgroundImage = `url('${pair[0]}')`;
      top.setAttribute("aria-hidden", "true");

      const bottom = document.createElement("div");
      bottom.className = "segment-half";
      bottom.style.backgroundImage = `url('${pair[1]}')`;
      bottom.setAttribute("aria-hidden", "true");

      segment.append(top, bottom);
      segment.setAttribute("title", `Część ${partIndex + 1}`);
      collage.appendChild(segment);
    });

    const title = document.createElement("h3");
    title.textContent = device.pl;

    const controls = document.createElement("div");
    controls.className = "controls";

    const minus = document.createElement("button");
    minus.className = "minus-btn";
    minus.textContent = "−";
    minus.type = "button";
    minus.setAttribute("aria-label", `Zmniejsz ilość: ${device.pl}`);

    const qty = document.createElement("span");
    qty.className = "qty-value";
    qty.dataset.device = device.id;
    qty.textContent = orders[activeUser][device.id];

    minus.addEventListener("click", (event) => {
      event.stopPropagation();
      if (orders[activeUser][device.id] <= 0) return;
      orders[activeUser][device.id] -= 1;
      saveOrders(orders);
      qty.textContent = orders[activeUser][device.id];
    });

    controls.append(minus, qty);

    card.addEventListener("click", () => {
      orders[activeUser][device.id] += 1;
      saveOrders(orders);
      qty.textContent = orders[activeUser][device.id];
    });

    card.append(collage, title, controls);
    flavorGrid.appendChild(card);
  });

  if (adminForm && adminPassword && adminMessage) {
    adminForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (adminPassword.value === ADMIN_PASSWORD) {
        window.location.href = "admin.html";
        return;
      }

      adminMessage.textContent = "Nieprawidłowe hasło.";
      adminPassword.value = "";
    });
  }

  refreshUserButtons();
}

function formatFlavorForHeader(flavorText) {
  return flavorText.split("/").map((part) => part.trim()).join("<br>");
}

function renderBulkSummary(orders) {
  const output = document.getElementById("bulkOrderText");
  if (!output) return;

  const lines = DEVICES.map((device) => {
    const total = USERS.reduce((sum, user) => sum + orders[user][device.id], 0);
    return `- ${device.summaryEn} - ${total}`;
  });

  output.value = lines.join("\n");
}

function renderAdminPage() {
  const table = document.getElementById("ordersTable");
  const resetBtn = document.getElementById("resetOrdersBtn");

  if (!table || !resetBtn) return;

  function drawTable() {
    const orders = loadOrders();
    table.innerHTML = "";

    const thead = document.createElement("thead");
    const headRow = document.createElement("tr");
    const corner = document.createElement("th");
    corner.textContent = "Użytkownik / Vape";
    corner.className = "sticky-col";
    headRow.appendChild(corner);

    DEVICES.forEach((device) => {
      const th = document.createElement("th");
      th.innerHTML = formatFlavorForHeader(device.pl);
      headRow.appendChild(th);
    });

    thead.appendChild(headRow);

    const tbody = document.createElement("tbody");

    USERS.forEach((user) => {
      const row = document.createElement("tr");
      const userCell = document.createElement("th");
      userCell.className = "sticky-col";
      userCell.textContent = user;
      row.appendChild(userCell);

      DEVICES.forEach((device) => {
        const td = document.createElement("td");
        td.textContent = orders[user][device.id];
        row.appendChild(td);
      });

      tbody.appendChild(row);
    });

    table.append(thead, tbody);
    renderBulkSummary(orders);
  }

  resetBtn.addEventListener("click", () => {
    if (!confirm("Na pewno zresetować wszystkie zamówienia?")) return;
    saveOrders(defaultOrders());
    drawTable();
  });

  drawTable();
}

renderOrderingPage();
renderAdminPage();
