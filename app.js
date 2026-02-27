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
  const response = await fetch('/api/data');
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
  const response = await fetch('/api/orders', {
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
  const response = await fetch('/api/users', {
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

  function refreshUserButtons() {
    userPicker.querySelectorAll('button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.user === activeUser);
    });
    activeUserLabel.textContent = activeUser ? `Aktywny: ${activeUser}` : 'Najpierw wybierz użytkownika';
  }

  function renderDeviceCounts() {
    flavorGrid.querySelectorAll('.qty-value').forEach((node) => {
      const deviceId = node.dataset.device;
      node.textContent = activeUser ? orders[activeUser][deviceId] : '0';
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

    const title = document.createElement('h3');
    title.textContent = device.pl;

    const controls = document.createElement('div');
    controls.className = 'controls';

    const minus = document.createElement('button');
    minus.className = 'minus-btn';
    minus.textContent = '−';
    minus.type = 'button';
    minus.setAttribute('aria-label', `Zmniejsz ilość: ${device.pl}`);

    const qty = document.createElement('span');
    qty.className = 'qty-value';
    qty.dataset.device = device.id;
    qty.textContent = '0';

    minus.addEventListener('click', async (event) => {
      event.stopPropagation();
      if (!activeUser || orders[activeUser][device.id] <= 0) return;
      orders[activeUser][device.id] -= 1;
      qty.textContent = orders[activeUser][device.id];
      await saveOrders(orders);
    });

    controls.append(minus, qty);

    card.addEventListener('click', async () => {
      if (!activeUser) return;
      orders[activeUser][device.id] += 1;
      qty.textContent = orders[activeUser][device.id];
      await saveOrders(orders);
    });

    card.append(image, title, controls);
    flavorGrid.appendChild(card);
  });

  if (modalForm && modalInput && modalMessage) {
    modalForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!pendingUser) return;

      const expectedUser = users.find((user) => user.name === pendingUser);
      if (expectedUser && modalInput.value === expectedUser.password) {
        activeUser = pendingUser;
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

function formatFlavorForHeader(flavorText) {
  return flavorText.split('/').map((part) => part.trim()).join('<br>');
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

async function renderAdminPage() {
  const table = document.getElementById('ordersTable');
  const resetBtn = document.getElementById('resetOrdersBtn');
  const addUserForm = document.getElementById('addUserForm');
  const newUserName = document.getElementById('newUserName');
  const newUserPassword = document.getElementById('newUserPassword');
  const addUserMessage = document.getElementById('addUserMessage');
  const usersList = document.getElementById('usersList');

  if (!table || !resetBtn) return;

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
      th.innerHTML = formatFlavorForHeader(device.pl);
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

renderOrderingPage();
renderAdminPage();
