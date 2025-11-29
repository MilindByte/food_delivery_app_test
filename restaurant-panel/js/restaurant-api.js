// Restaurant Panel API Client
const REST_API_BASE_URL = 'http://localhost/agravity/food-delivery-app/api';

class RestaurantAPI {
    // Helper method to make API calls
    static async request(endpoint, options = {}) {
        const url = `${REST_API_BASE_URL}/${endpoint}`;

        const config = {
            ...options,
            credentials: 'include',
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
    static async login(username, password) {
        return this.request('restaurant-auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
    }

    static async register(restaurantData) {
        return this.request('restaurant-auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(restaurantData)
        });
    }

    static async logout() {
        return this.request('restaurant-auth.php?action=logout', {
            method: 'POST'
        });
    }

    static async checkAuth() {
        return this.request('restaurant-auth.php?action=check');
    }

    // Orders APIs
    static async getOrders(status = null) {
        const params = status ? `?status=${status}` : '';
        return this.request(`restaurant-orders.php${params}`);
    }

    static async getOrder(id) {
        return this.request(`restaurant-orders.php?id=${id}`);
    }

    static async updateOrderStatus(orderId, status) {
        return this.request('restaurant-orders.php', {
            method: 'PUT',
            body: JSON.stringify({ order_id: orderId, status })
        });
    }

    // Menu APIs
    static async getMenu() {
        return this.request('restaurant-menu.php');
    }

    static async addMenuItem(item) {
        return this.request('restaurant-menu.php', {
            method: 'POST',
            body: JSON.stringify(item)
        });
    }

    static async updateMenuItem(itemId, updates) {
        return this.request('restaurant-menu.php', {
            method: 'PUT',
            body: JSON.stringify({ item_id: itemId, ...updates })
        });
    }

    static async deleteMenuItem(itemId) {
        return this.request(`restaurant-menu.php?item_id=${itemId}`, {
            method: 'DELETE'
        });
    }
}
