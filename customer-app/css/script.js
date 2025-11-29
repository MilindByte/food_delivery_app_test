// Wait for DOM to load
document.addEventListener('DOMContentLoaded', async function () {
    const navbar = document.getElementById('mainNav');
    const restaurantGrid = document.getElementById('restaurantGrid');
    const loadingSpinner = document.getElementById('loadingSpinner');

    // Navbar scroll effect
    window.addEventListener('scroll', function () {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
            navbar.classList.remove('bg-transparent');
        } else {
            navbar.classList.remove('scrolled');
            navbar.classList.add('bg-transparent');
        }
    });

    // Simple interaction for filters  
    const filterBtns = document.querySelectorAll('.btn-outline-secondary');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            this.classList.toggle('active');
            this.classList.toggle('btn-outline-secondary');
            this.classList.toggle('btn-danger');
            this.classList.toggle('text-white');
        });
    });

    // Check authentication status
    await checkAuthStatus();

    // Load restaurants if on index page
    if (restaurantGrid) {
        await loadRestaurants();
    }
});

// Check authentication status
async function checkAuthStatus() {
    try {
        const response = await API.checkAuth();
        const authNav = document.getElementById('authNav');

        if (response.authenticated && response.user) {
            // User is logged in
            if (authNav) {
                authNav.innerHTML = `
                    <a class="nav-link text-white dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> ${response.user.name}
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="orders.html">My Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" id="logoutBtn">Logout</a></li>
                    </ul>
                `;

                // Add logout handler
                document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
            }
        } else {
            // User is not logged in
            if (authNav) {
                authNav.innerHTML = `<a class="nav-link text-white" href="login.html">Log in</a>`;
            }
        }
    } catch (error) {
        console.error('Failed to check auth status:', error);
    }
}

// Handle logout
async function handleLogout(e) {
    e.preventDefault();
    try {
        await API.logout();
        window.location.href = 'index.html';
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

// Load restaurants
async function loadRestaurants() {
    const restaurantGrid = document.getElementById('restaurantGrid');
    const loadingSpinner = document.getElementById('loadingSpinner');

    try {
        const response = await API.getRestaurants();
        const restaurants = response.data;

        // Hide loading spinner
        if (loadingSpinner) {
            loadingSpinner.remove();
        }

        // Render restaurants
        restaurants.forEach(restaurant => {
            const restaurantCard = createRestaurantCard(restaurant);
            restaurantGrid.innerHTML += restaurantCard;
        });
    } catch (error) {
        console.error('Failed to load restaurants:', error);
        if (loadingSpinner) {
            loadingSpinner.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-exclamation-triangle text-danger fs-1"></i>
                    <p class="mt-3 text-danger">Failed to load restaurants. Please try again later.</p>
                </div>
            `;
        }
    }
}

// Create restaurant card HTML
function createRestaurantCard(restaurant) {
    const imageUrl = restaurant.image_url || 'https://via.placeholder.com/400x300?text=Restaurant';
    const discountText = restaurant.discount_text || '';

    return `
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 restaurant-card" onclick="window.location.href='restaurant.html?id=${restaurant.id}'">
                <div class="position-relative">
                    <img src="${imageUrl}" class="card-img-top rounded-3" alt="${restaurant.name}" onerror="this.src='https://via.placeholder.com/400x300?text=Restaurant'">
                    <span class="position-absolute top-0 end-0 bg-white px-2 py-1 m-2 rounded small fw-bold shadow-sm">${restaurant.delivery_time}</span>
                    ${discountText ? `<span class="position-absolute bottom-0 start-0 bg-primary text-white px-2 py-1 m-2 rounded small fw-bold">${discountText}</span>` : ''}
                </div>
                <div class="card-body px-0">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h5 class="card-title fw-bold mb-0">${restaurant.name}</h5>
                        <span class="badge bg-success">${restaurant.rating} <i class="fas fa-star small"></i></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                        <span>${restaurant.cuisine_types}</span>
                        <span>₹${restaurant.price_for_one} for one</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex align-items-center small text-primary">
                        <img src="https://b.zmtcdn.com/data/o2_assets/4bf016f32f05d26242cea342f30d47a31595763089.png" alt="Safety" style="width: 18px;" class="me-2">
                        <span>Follows all Max Safety measures</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

