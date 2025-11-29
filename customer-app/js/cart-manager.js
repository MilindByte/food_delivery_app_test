// Cart Manager - Client-side cart state management
class CartManager {
    constructor() {
        this.cart = null;
        this.listeners = [];
        this.init();
    }

    async init() {
        await this.loadCart();
        this.updateCartBadge();
    }

    // Load cart from API
    async loadCart() {
        try {
            const response = await API.getCart();
            this.cart = response;
            this.notifyListeners();
            return this.cart;
        } catch (error) {
            console.error('Failed to load cart:', error);
            this.cart = { data: [], summary: { item_count: 0, total: 0 } };
            return this.cart;
        }
    }

    // Add item to cart
    async addItem(menuItemId, quantity = 1) {
        try {
            await API.addToCart(menuItemId, quantity);
            await this.loadCart();
            this.updateCartBadge();
            this.showNotification('Item added to cart!', 'success');
            return true;
        } catch (error) {
            this.showNotification(error.message || 'Failed to add item', 'error');
            return false;
        }
    }

    // Update item quantity
    async updateItem(cartId, quantity) {
        try {
            await API.updateCartItem(cartId, quantity);
            await this.loadCart();
            this.updateCartBadge();
            return true;
        } catch (error) {
            this.showNotification(error.message || 'Failed to update item', 'error');
            return false;
        }
    }

    // Remove item from cart
    async removeItem(cartId) {
        try {
            await API.removeFromCart(cartId);
            await this.loadCart();
            this.updateCartBadge();
            this.showNotification('Item removed from cart', 'success');
            return true;
        } catch (error) {
            this.showNotification(error.message || 'Failed to remove item', 'error');
            return false;
        }
    }

    // Clear entire cart
    async clearCart() {
        try {
            await API.clearCart();
            await this.loadCart();
            this.updateCartBadge();
            this.showNotification('Cart cleared', 'success');
            return true;
        } catch (error) {
            this.showNotification(error.message || 'Failed to clear cart', 'error');
            return false;
        }
    }

    // Get current cart
    getCart() {
        return this.cart;
    }

    // Get item count
    getItemCount() {
        return this.cart?.summary?.item_count || 0;
    }

    // Get total amount
    getTotal() {
        return this.cart?.summary?.total || 0;
    }

    // Subscribe to cart changes
    subscribe(callback) {
        this.listeners.push(callback);
    }

    // Notify all listeners
    notifyListeners() {
        this.listeners.forEach(callback => callback(this.cart));
    }

    // Update cart badge in navbar
    updateCartBadge() {
        const badge = document.getElementById('cartBadge');
        const count = this.getItemCount();

        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    // Show notification
    showNotification(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        // Add to toast container
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Remove after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

// Create global cart manager instance
const cartManager = new CartManager();

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CartManager;
}
