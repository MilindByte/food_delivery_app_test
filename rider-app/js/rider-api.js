// Rider App API Client
const RIDER_API_BASE_URL = 'http://localhost/agravity/food-delivery-app/api';

class RiderAPI {
    // Helper method to make API calls
    static async request(endpoint, options = {}) {
        const url = `${RIDER_API_BASE_URL}/${endpoint}`;
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
    static async login(email, password) {
        return this.request('rider-auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    static async register(riderData) {
        return this.request('rider-auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(riderData)
        });
    }

    static async logout() {
        return this.request('rider-auth.php?action=logout', {
            method: 'POST'
        });
    }

    static async checkAuth() {
        return this.request('rider-auth.php?action=check');
    }

    static async toggleAvailability() {
        return this.request('rider-auth.php?action=toggle-availability', {
            method: 'PUT'
        });
    }

    // Orders APIs
    static async getAvailableOrders() {
        return this.request('rider-orders.php?action=available');
    }

    static async getAssignedOrders() {
        return this.request('rider-orders.php?action=assigned');
    }

    static async getHistory() {
        return this.request('rider-orders.php?action=history');
    }

    static async acceptOrder(orderId) {
        return this.request('rider-orders.php?action=accept', {
            method: 'POST',
            body: JSON.stringify({ order_id: orderId })
        });
    }

    static async updateOrderStatus(orderId, status) {
        return this.request('rider-orders.php?action=update-status', {
            method: 'PUT',
            body: JSON.stringify({ order_id: orderId, status })
        });
    }

    // Earnings API
    static async getEarnings() {
        return this.request('rider-orders.php?action=earnings');
    }
}
