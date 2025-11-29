// API Client for Food Delivery App
const API_BASE_URL = 'http://localhost/agravity/food-delivery-app/api';

class API {
    // Helper method to make API calls
    static async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}/${endpoint}`;

        const config = {
            ...options,
            credentials: 'include', // Include cookies for session
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Authentication APIs
    static async login(email, password) {
        return this.request('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    static async register(name, email, password, phone, address) {
        return this.request('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify({ name, email, password, phone, address })
        });
    }

    static async logout() {
        return this.request('auth.php?action=logout', {
            method: 'POST'
        });
    }

    static async checkAuth() {
        return this.request('auth.php?action=check');
    }

    // Restaurant APIs
    static async getRestaurants(filters = {}) {
        const params = new URLSearchParams(filters);
        return this.request(`restaurants.php?${params}`);
    }

    static async getRestaurant(id) {
        return this.request(`restaurants.php?id=${id}`);
    }

    // Menu APIs
    static async getMenu(restaurantId) {
        return this.request(`menu.php?restaurant_id=${restaurantId}`);
    }

    // Cart APIs
    static async getCart() {
        return this.request('cart.php');
    }

    static async addToCart(menuItemId, quantity = 1) {
        return this.request('cart.php', {
            method: 'POST',
            body: JSON.stringify({ menu_item_id: menuItemId, quantity })
        });
    }

    static async updateCartItem(cartId, quantity) {
        return this.request('cart.php', {
            method: 'PUT',
            body: JSON.stringify({ cart_id: cartId, quantity })
        });
    }

    static async removeFromCart(cartId) {
        return this.request(`cart.php?cart_id=${cartId}`, {
            method: 'DELETE'
        });
    }

    static async clearCart() {
        return this.request('cart.php?clear=1', {
            method: 'DELETE'
        });
    }

    // Order APIs
    static async getOrders() {
        return this.request('orders.php');
    }

    static async getOrder(id) {
        return this.request(`orders.php?id=${id}`);
    }

    static async placeOrder(deliveryAddress, paymentMethod) {
        return this.request('orders.php', {
            method: 'POST',
            body: JSON.stringify({ delivery_address: deliveryAddress, payment_method: paymentMethod })
        });
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
