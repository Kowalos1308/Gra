const DEVICES = [
  {
    id: "watermelon-ice-strawberry-red-bull-strawberry-kiwi",
    summaryEn: "Watermelon Ice/ Strawberry Red Bull/Strawberry Kiwi",
    pl: "Arbuz Ice / Truskawka Red Bull / Truskawka Kiwi",
    image: "images/1.jpg"
  },
  {
    id: "strawberry-watermelon-bluberry-on-ice-kiwi-lemon",
    summaryEn: "Strawberry Watermelon/Bluberry On Ice/Kiwi Lemon",
    pl: "Truskawka Arbuz / Borówka Ice / Kiwi Cytryna",
    image: "images/2.jpg"
  },
  {
    id: "mixed-berries-peach-apple-ice-lady-killer",
    summaryEn: "Mixed Berries/Peach Apple Ice/Lady Killer",
    pl: "Leśne Owoce / Brzoskwinia Jabłko Ice / Lady Killer",
    image: "images/3.jpg"
  },
  {
    id: "mango-ice-banana-ice-blue-razz-lemoniade",
    summaryEn: "Mango Ice/Banana Ice/Blue Razz Lemoniade",
    pl: "Mango Ice / Banan Ice / Niebieska Malina Lemoniada",
    image: "images/4.jpg"
  },
  {
    id: "peach-mango-pineapple-strawberry-raspberry-ice-triple-mango",
    summaryEn: "Peach Mango Pineapple/Strawberry Raspberry Ice/Triple Mango",
    pl: "Brzoskwinia Mango Ananas / Truskawka Malina Ice / Potrójne Mango",
    image: "images/5.jpg"
  },
  {
    id: "strawberry-grape-peach-mango-ice-pop",
    summaryEn: "Strawberry Grape/Peach Mango/Ice Pop",
    pl: "Truskawka Winogrono / Brzoskwinia Mango / Ice Pop",
    image: "images/6.jpg"
  },
  {
    id: "strawberry-ice-sour-apple-bluberry-raspberry",
    summaryEn: "Strawberry Ice/Sour Apple/Bluberry Raspberry",
    pl: "Truskawka Ice / Kwaśne Jabłko / Borówka Malina",
    image: "images/7.jpg"
  },
  {
    id: "grape-ice-cherry-ice-blue-sour-raspberry",
    summaryEn: "Grape Ice/Cherry Ice/Blue Sour Raspberry",
    pl: "Winogrono Ice / Wiśnia Ice / Kwaśna Niebieska Malina",
    image: "images/8.jpg"
  },
  {
    id: "peach-ice-red-apple-bluberry-cherry-cranberry",
    summaryEn: "Peach Ice/Red Apple/Bluberry Cherry Cranberry",
    pl: "Brzoskwinia Ice / Czerwone Jabłko / Borówka Wiśnia Żurawina",
    image: "images/9.jpg"
  },
  {
    id: "kiwi-passion-fruit-guava-green-apple-juicy-peach",
    summaryEn: "Kiwi Passion Fruit Guava/Green Apple/Juicy Peach",
    pl: "Kiwi Marakuja Guawa / Zielone Jabłko / Soczysta Brzoskwinia",
    image: "images/10.jpg"
  }
];

const API_URL = new URL('api.php', window.location.href);

const ACTIVE_USER_COOKIE = 'activeVapeUser';
const ACTIVE_USER_COOKIE_MAX_AGE_SECONDS = 60 * 60;
const OWN_ORDER_USER = 'Wera';

function setCookie(name, value, maxAgeSeconds) {
  document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; max-age=${maxAgeSeconds}; path=/; SameSite=Lax`;
}

function getCookie(name) {
  const key = `${encodeURIComponent(name)}=`;
  const parts = document.cookie.split(';').map((part) => part.trim());
  const matched = parts.find((part) => part.startsWith(key));
  if (!matched) return '';
  return decodeURIComponent(matched.slice(key.length));
}

function clearCookie(name) {
  document.cookie = `${encodeURIComponent(name)}=; max-age=0; path=/; SameSite=Lax`;
}


function makeApiUrl(action) {
  const url = new URL(API_URL);
  url.searchParams.set('action', action);
  return url.toString();
}

function extractFlavorSegments(flavorText) {
  return flavorText
    .split('/')
    .map((part) => part.trim())
    .filter(Boolean)
    .slice(0, 3);
}

function formatMinusLabel(quantity) {
  return `− ${quantity} | Zmniejsz`;
}

function showFatalError(message) {
  const panels = document.querySelectorAll('.panel');
  if (!panels.length) return;

  const errorNode = document.createElement('p');
  errorNode.className = 'access-message';
  errorNode.style.color = '#fca5a5';
  errorNode.textContent = message;
  panels[0].prepend(errorNode);
}

function createEmptyOrders(users) {
  return Object.fromEntries(
    users.map((user) => [user.name, Object.fromEntries(DEVICES.map((device) => [device.id, 0]))])
  );
}

function normalizeOrders(users, rawOrders) {
  const output = createEmptyOrders(users);

  users.forEach((user) => {
    const source = rawOrders?.[user.name] || {};
    DEVICES.forEach((device) => {
      const value = Number(source[device.id]);
      output[user.name][device.id] = Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
    });
  });

  return output;
}

async function fetchData() {
  const response = await fetch(makeApiUrl('data'));
  if (!response.ok) {
    throw new Error('Nie udało się pobrać danych z serwera.');
  }

  const data = await response.json();
  const users = Array.isArray(data.users) ? data.users : [];

  return {
    ...data,
    users,
    orders: normalizeOrders(users, data.orders)
  };
}

async function saveOrders(orders) {
  const response = await fetch(makeApiUrl('orders'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ orders })
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.error || 'Nie udało się zapisać zamówień.');
  }
}

async function addUser(name, password) {
  const response = await fetch(makeApiUrl('users'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, password, deviceIds: DEVICES.map((device) => device.id) })
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Nie udało się dodać użytkownika.');
  }

  return payload;
}

async function renderOrderingPage() {
  const userPicker = document.getElementById('userPicker');
  const flavorGrid = document.getElementById('flavorGrid');
  const activeUserLabel = document.getElementById('activeUserLabel');
  const resetMyOrderBtn = document.getElementById('resetMyOrderBtn');
  const adminGate = document.getElementById('adminGate');
  const adminForm = document.getElementById('adminAccessForm');
  const adminPassword = document.getElementById('adminPassword');
  const adminMessage = document.getElementById('adminAccessMessage');

  const modal = document.getElementById('userPasswordModal');
  const modalSubtitle = document.getElementById('userPasswordSubtitle');
  const modalForm = document.getElementById('userPasswordForm');
  const modalInput = document.getElementById('userPasswordInput');
  const modalMessage = document.getElementById('userPasswordMessage');
  const modalCancel = document.getElementById('userPasswordCancel');

  if (!userPicker || !flavorGrid || !activeUserLabel) return;

  const data = await fetchData();
  const users = data.users;
  const orders = data.orders;
  let activeUser = null;
  let pendingUser = null;
  const rememberedUser = getCookie(ACTIVE_USER_COOKIE);

  function refreshUserButtons() {
    userPicker.querySelectorAll('button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.user === activeUser);
    });
    activeUserLabel.textContent = activeUser ? `Aktywny: ${activeUser}` : 'Najpierw wybierz użytkownika';

    if (resetMyOrderBtn) {
      resetMyOrderBtn.classList.toggle('hidden', !activeUser);
    }

    if (adminGate) {
      const canSeeAdminGate = activeUser === OWN_ORDER_USER;
      adminGate.classList.toggle('hidden', !canSeeAdminGate);
    }
  }

  function renderDeviceCounts() {
    flavorGrid.querySelectorAll('.minus-btn[data-device]').forEach((button) => {
      const deviceId = button.dataset.device;
      const quantity = activeUser ? orders[activeUser][deviceId] : 0;
      button.textContent = formatMinusLabel(quantity);
    });
  }

  function openPasswordModal(user) {
    if (!modal || !modalSubtitle || !modalInput || !modalMessage) return;
    pendingUser = user;
    modalSubtitle.textContent = `Użytkownik: ${user}`;
    modalInput.value = '';
    modalMessage.textContent = '';
    modal.classList.remove('hidden');
    modalInput.focus();
  }

  function closePasswordModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    pendingUser = null;
  }

  users.forEach((user) => {
    const btn = document.createElement('button');
    btn.className = 'user-btn';
    btn.textContent = user.name;
    btn.dataset.user = user.name;
    btn.addEventListener('click', () => openPasswordModal(user.name));
    userPicker.appendChild(btn);
  });

  DEVICES.forEach((device) => {
    const card = document.createElement('article');
    card.className = 'flavor-card';

    const image = document.createElement('div');
    image.className = 'flavor-image';
    image.style.backgroundImage = `url('${device.image}')`;

    const segmentLayer = document.createElement('div');
    segmentLayer.className = 'flavor-segments';

    const segments = extractFlavorSegments(device.pl);
    segments.forEach((segmentText) => {
      const segment = document.createElement('div');
      segment.className = 'flavor-segment';
      segment.textContent = segmentText;
      segmentLayer.appendChild(segment);
    });

    image.appendChild(segmentLayer);

    const controls = document.createElement('div');
    controls.className = 'controls';

    const minus = document.createElement('button');
    minus.className = 'minus-btn';
    minus.type = 'button';
    minus.dataset.device = device.id;
    minus.textContent = formatMinusLabel(0);
    minus.setAttribute('aria-label', `Zmniejsz ilość: ${device.pl}`);

    minus.addEventListener('click', async (event) => {
      event.stopPropagation();
      if (!activeUser || orders[activeUser][device.id] <= 0) return;
      orders[activeUser][device.id] -= 1;
      minus.textContent = formatMinusLabel(orders[activeUser][device.id]);
      await saveOrders(orders);
    });

    controls.append(minus);

    card.addEventListener('click', async () => {
      if (!activeUser) return;
      orders[activeUser][device.id] += 1;
      minus.textContent = formatMinusLabel(orders[activeUser][device.id]);
      await saveOrders(orders);
    });

    card.append(image, controls);
    flavorGrid.appendChild(card);
  });

  if (modalForm && modalInput && modalMessage) {
    modalForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!pendingUser) return;

      const expectedUser = users.find((user) => user.name === pendingUser);
      if (expectedUser && modalInput.value === expectedUser.password) {
        activeUser = pendingUser;
        setCookie(ACTIVE_USER_COOKIE, activeUser, ACTIVE_USER_COOKIE_MAX_AGE_SECONDS);
        closePasswordModal();
        refreshUserButtons();
        renderDeviceCounts();
        return;
      }

      modalMessage.textContent = 'Nieprawidłowe hasło użytkownika.';
      modalInput.value = '';
      modalInput.focus();
    });
  }

  if (modalCancel) {
    modalCancel.addEventListener('click', () => closePasswordModal());
  }

  if (modal) {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closePasswordModal();
    });
  }

  if (rememberedUser) {
    const isRememberedUserValid = users.some((user) => user.name === rememberedUser);
    if (isRememberedUserValid) {
      activeUser = rememberedUser;
    } else {
      clearCookie(ACTIVE_USER_COOKIE);
    }
  }

  if (resetMyOrderBtn) {
    resetMyOrderBtn.addEventListener('click', async () => {
      if (!activeUser) return;
      if (!confirm('Na pewno zresetować Twoje zamówienie?')) return;

      DEVICES.forEach((device) => {
        orders[activeUser][device.id] = 0;
      });

      renderDeviceCounts();
      await saveOrders(orders);
    });
  }

  if (adminForm && adminPassword && adminMessage) {
    adminForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (adminPassword.value === data.adminPassword) {
        window.location.href = 'admin.html';
        return;
      }

      adminMessage.textContent = 'Nieprawidłowe hasło.';
      adminPassword.value = '';
    });
  }

  refreshUserButtons();
  renderDeviceCounts();
}

function formatFlavorForHeader(device) {
  const pl = device.pl.split('/').map((part) => part.trim()).join('<br>');
  return `${pl}<span class="header-en">${device.summaryEn}</span>`;
}

function renderBulkSummary(orders, users) {
  const output = document.getElementById('bulkOrderText');
  if (!output) return;

  const lines = DEVICES.map((device) => {
    const total = users.reduce((sum, user) => sum + orders[user.name][device.id], 0);
    return `- ${device.summaryEn} - ${total}`;
  });

  output.value = lines.join('\n');
}

function countUserItems(orders, username) {
  return DEVICES.reduce((sum, device) => sum + (orders[username]?.[device.id] || 0), 0);
}

function formatCurrency(value) {
  return `${value.toFixed(2)} zł`;
}

function drawCalculationRows(users, orders, priceMap, body) {
  body.innerHTML = '';

  users.forEach((user) => {
    const row = document.createElement('tr');

    const userCell = document.createElement('td');
    userCell.textContent = user.name;

    const qtyCell = document.createElement('td');
    const quantity = countUserItems(orders, user.name);
    qtyCell.textContent = String(quantity);

    const priceCell = document.createElement('td');
    const priceInput = document.createElement('input');
    priceInput.type = 'number';
    priceInput.step = '0.01';
    priceInput.min = '0';
    priceInput.className = 'calc-input';

    if (user.name === OWN_ORDER_USER) {
      priceMap[user.name] = '';
      priceInput.value = '';
      priceInput.placeholder = 'Twoje zamówienie';
      priceInput.disabled = true;
      priceInput.classList.add('calc-input-own');
    } else {
      if (!(user.name in priceMap)) {
        priceMap[user.name] = '';
      }
      priceInput.placeholder = 'np. 42';
      priceInput.value = priceMap[user.name] === '' ? '' : String(priceMap[user.name]);
      priceInput.addEventListener('input', () => {
        priceMap[user.name] = priceInput.value;
      });
    }

    priceCell.appendChild(priceInput);

    const sumCell = document.createElement('td');
    sumCell.textContent = user.name === OWN_ORDER_USER ? 'Moje zamówienie' : '—';
    sumCell.dataset.userTotal = user.name;

    row.append(userCell, qtyCell, priceCell, sumCell);
    body.appendChild(row);
  });
}

function recalculateSummary(users, orders, priceMap, purchasePrice, summaryPrepare, summaryOwnPrice, calcBody) {
  const totalQty = users.reduce((sum, user) => sum + countUserItems(orders, user.name), 0);
  const preparedToPay = purchasePrice > 0 ? totalQty * purchasePrice : 0;

  let othersContribution = 0;

  users.forEach((user) => {
    const quantity = countUserItems(orders, user.name);
    const totalCell = calcBody.querySelector(`[data-user-total="${user.name}"]`);

    if (user.name === OWN_ORDER_USER) {
      if (totalCell) totalCell.textContent = 'Moje zamówienie';
      return;
    }

    const userPrice = Number(priceMap[user.name]);
    if (!Number.isFinite(userPrice) || userPrice <= 0 || quantity <= 0) {
      if (totalCell) totalCell.textContent = '—';
      return;
    }

    const received = quantity * userPrice;
    othersContribution += received;

    if (totalCell) totalCell.textContent = formatCurrency(received);
  });

  const ownQty = countUserItems(orders, OWN_ORDER_USER);
  const ownUnitPrice = ownQty > 0 ? (preparedToPay - othersContribution) / ownQty : 0;

  summaryPrepare.textContent = `Przygotuj do zapłaty: ${formatCurrency(preparedToPay)}`;
  summaryOwnPrice.textContent = `Twoja cena za szt.: ${formatCurrency(ownUnitPrice)}`;
}

function renderIssueTable(issueTable, users, orders) {
  if (!issueTable) return;

  const activeUsers = users.filter((user) => countUserItems(orders, user.name) > 0);
  issueTable.innerHTML = '';

  if (!activeUsers.length) {
    const empty = document.createElement('caption');
    empty.className = 'issue-empty';
    empty.textContent = 'Brak użytkowników z zamówieniem.';
    issueTable.appendChild(empty);
    return;
  }

  const thead = document.createElement('thead');
  const headRow = document.createElement('tr');
  const userCorner = document.createElement('th');
  userCorner.textContent = 'Użytkownik';
  userCorner.className = 'sticky-col';
  headRow.appendChild(userCorner);

  DEVICES.forEach((device) => {
    const th = document.createElement('th');
    th.innerHTML = `<div class="issue-header"><img src="${device.image}" alt="${device.summaryEn}" /><span>${device.summaryEn}</span></div>`;
    headRow.appendChild(th);
  });

  thead.appendChild(headRow);

  const tbody = document.createElement('tbody');
  activeUsers.forEach((user) => {
    const row = document.createElement('tr');
    const userCell = document.createElement('th');
    userCell.className = 'sticky-col';
    userCell.textContent = user.name;
    row.appendChild(userCell);

    DEVICES.forEach((device) => {
      const td = document.createElement('td');
      const qty = orders[user.name][device.id];
      td.textContent = qty > 0 ? String(qty) : '—';
      row.appendChild(td);
    });

    tbody.appendChild(row);
  });

  issueTable.append(thead, tbody);
}

async function renderAdminPage() {
  const table = document.getElementById('ordersTable');
  const issueTable = document.getElementById('issueTable');
  const resetBtn = document.getElementById('resetOrdersBtn');
  const addUserForm = document.getElementById('addUserForm');
  const newUserName = document.getElementById('newUserName');
  const newUserPassword = document.getElementById('newUserPassword');
  const addUserMessage = document.getElementById('addUserMessage');
  const usersList = document.getElementById('usersList');
  const purchasePriceInput = document.getElementById('purchasePriceInput');
  const recalcBtn = document.getElementById('recalculateBtn');
  const calcBody = document.getElementById('calcTableBody');
  const summaryPrepare = document.getElementById('summaryPrepare');
  const summaryOwnPrice = document.getElementById('summaryOwnPrice');

  if (!table || !resetBtn) return;

  const priceMap = {};

  async function drawTable() {
    const data = await fetchData();
    const users = data.users;
    const orders = data.orders;

    table.innerHTML = '';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    const corner = document.createElement('th');
    corner.textContent = 'Użytkownik / Vape';
    corner.className = 'sticky-col';
    headRow.appendChild(corner);

    DEVICES.forEach((device) => {
      const th = document.createElement('th');
      th.innerHTML = formatFlavorForHeader(device);
      headRow.appendChild(th);
    });

    thead.appendChild(headRow);

    const tbody = document.createElement('tbody');

    users.forEach((user) => {
      const row = document.createElement('tr');
      const userCell = document.createElement('th');
      userCell.className = 'sticky-col';
      userCell.textContent = user.name;
      row.appendChild(userCell);

      DEVICES.forEach((device) => {
        const td = document.createElement('td');
        td.textContent = orders[user.name][device.id];
        row.appendChild(td);
      });

      tbody.appendChild(row);
    });

    table.append(thead, tbody);
    renderBulkSummary(orders, users);
    renderIssueTable(issueTable, users, orders);

    if (calcBody && summaryPrepare && summaryOwnPrice && purchasePriceInput) {
      users.forEach((user) => {
        if (!(user.name in priceMap)) {
          priceMap[user.name] = '';
        }
      });
      drawCalculationRows(users, orders, priceMap, calcBody);
      const purchasePrice = Number(purchasePriceInput.value);
      recalculateSummary(
        users,
        orders,
        priceMap,
        Number.isFinite(purchasePrice) && purchasePrice > 0 ? purchasePrice : 0,
        summaryPrepare,
        summaryOwnPrice,
        calcBody
      );
    }

    if (usersList) {
      usersList.innerHTML = '';
      users.forEach((user) => {
        const li = document.createElement('li');
        li.textContent = `${user.name} — hasło: ${user.password}`;
        usersList.appendChild(li);
      });
    }
  }

  resetBtn.addEventListener('click', async () => {
    const data = await fetchData();
    if (!confirm('Na pewno zresetować wszystkie zamówienia?')) return;
    await saveOrders(createEmptyOrders(data.users));
    await drawTable();
  });

  if (recalcBtn && purchasePriceInput && calcBody && summaryPrepare && summaryOwnPrice) {
    recalcBtn.addEventListener('click', async () => {
      const data = await fetchData();
      const users = data.users;
      const orders = data.orders;
      const purchasePrice = Number(purchasePriceInput.value);
      recalculateSummary(
        users,
        orders,
        priceMap,
        Number.isFinite(purchasePrice) && purchasePrice > 0 ? purchasePrice : 0,
        summaryPrepare,
        summaryOwnPrice,
        calcBody
      );
    });
  }

  if (addUserForm && newUserName && newUserPassword && addUserMessage) {
    addUserForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      try {
        await addUser(newUserName.value, newUserPassword.value);
        addUserMessage.style.color = '#86efac';
        addUserMessage.textContent = 'Użytkownik dodany.';
        addUserForm.reset();
        await drawTable();
      } catch (error) {
        addUserMessage.style.color = '#fca5a5';
        addUserMessage.textContent = error.message;
      }
    });
  }

  await drawTable();
}

(async () => {
  try {
    await renderOrderingPage();
    await renderAdminPage();
  } catch (error) {
    showFatalError(error.message || 'Wystąpił błąd ładowania danych.');
  }
})();
