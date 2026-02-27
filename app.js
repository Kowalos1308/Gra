const USERS = ["Klaudia", "Damian", "Wera"];

const DEVICES = [
  {
    id: "watermelon-ice-strawberry-red-bull-strawberry-kiwi",
    en: "Watermelon Ice / Strawberry Red Bull / Strawberry Kiwi",
    pl: "Arbuz Ice / Truskawka Red Bull / Truskawka Kiwi"
  },
  {
    id: "strawberry-watermelon-blueberry-on-ice-kiwi-lemon",
    en: "Strawberry Watermelon / Blueberry On Ice / Kiwi Lemon",
    pl: "Truskawka Arbuz / Borówka Ice / Kiwi Cytryna"
  },
  {
    id: "mixed-berries-peach-apple-ice-lady-killer",
    en: "Mixed Berries / Peach Apple Ice / Lady Killer",
    pl: "Leśne Owoce / Brzoskwinia Jabłko Ice / Lady Killer"
  },
  {
    id: "mango-ice-banana-ice-blue-razz-lemonade",
    en: "Mango Ice / Banana Ice / Blue Razz Lemonade",
    pl: "Mango Ice / Banan Ice / Niebieska Malina Lemoniada"
  },
  {
    id: "peach-mango-pineapple-strawberry-raspberry-ice-triple-mango",
    en: "Peach Mango Pineapple / Strawberry Raspberry Ice / Triple Mango",
    pl: "Brzoskwinia Mango Ananas / Truskawka Malina Ice / Potrójne Mango"
  },
  {
    id: "strawberry-grape-peach-mango-ice-pop",
    en: "Strawberry Grape / Peach Mango / Ice Pop",
    pl: "Truskawka Winogrono / Brzoskwinia Mango / Ice Pop"
  },
  {
    id: "strawberry-ice-sour-apple-blueberry-raspberry",
    en: "Strawberry Ice / Sour Apple / Blueberry Raspberry",
    pl: "Truskawka Ice / Kwaśne Jabłko / Borówka Malina"
  },
  {
    id: "grape-ice-cherry-ice-blue-sour-raspberry",
    en: "Grape Ice / Cherry Ice / Blue Sour Raspberry",
    pl: "Winogrono Ice / Wiśnia Ice / Kwaśna Niebieska Malina"
  },
  {
    id: "peach-ice-red-apple-blueberry-cherry-cranberry",
    en: "Peach Ice / Red Apple / Blueberry Cherry Cranberry",
    pl: "Brzoskwinia Ice / Czerwone Jabłko / Borówka Wiśnia Żurawina"
  },
  {
    id: "kiwi-passion-fruit-guava-green-apple-juicy-peach",
    en: "Kiwi Passion Fruit Guava / Green Apple / Juicy Peach",
    pl: "Kiwi Marakuja Guawa / Zielone Jabłko / Soczysta Brzoskwinia"
  }
];

const STORAGE_KEY = "vape-orders-v2";

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

  DEVICES.forEach((device, index) => {
    const card = document.createElement("article");
    card.className = "flavor-card";

    const image = document.createElement("img");
    image.src = `images/${index + 1}.jpg`;
    image.alt = `Zdjęcie vape: ${device.en}`;
    image.loading = "lazy";

    const title = document.createElement("h3");
    title.textContent = device.pl;

    const subtitle = document.createElement("p");
    subtitle.className = "en-name";
    subtitle.textContent = device.en;

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

    card.append(image, title, subtitle, controls);
    flavorGrid.appendChild(card);
  });

  refreshUserButtons();
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
    corner.textContent = "Użytkownik / Vape (3 smaki)";
    corner.className = "sticky-col";
    headRow.appendChild(corner);

    DEVICES.forEach((device) => {
      const th = document.createElement("th");
      th.title = device.en;
      th.textContent = device.pl;
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
