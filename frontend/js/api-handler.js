// frontend/js/api-handler.js

// Base URL của API Gateway
const API_BASE_URL = '/TransportationRenting/gateway/api';

/**
 * Call API với xử lý maintenance tự động
 * @param {string} endpoint - Endpoint API (VD: '/customers', '/vehicles', '/rentals')
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
 * @param {object|null} data - Dữ liệu gửi đi (cho POST/PUT)
 * @returns {Promise<object|null>} - Response data hoặc null nếu maintenance
 */
async function callAPI(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include' // Giữ session/cookies
        };
        
        // Thêm Authorization token nếu có
        const token = localStorage.getItem('auth_token');
        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Thêm body data cho POST/PUT/PATCH
        if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            options.body = JSON.stringify(data);
        }
        
        // Gọi API
        const fullUrl = `${API_BASE_URL}${endpoint}`;
        console.log(`🌐 Calling API: ${method} ${fullUrl}`);
        
        const response = await fetch(fullUrl, options);
        
        // Kiểm tra service maintenance (503)
        if (response.status === 503) {
            console.warn('⚠️ Service unavailable (503)');
            showMaintenancePage();
            return null;
        }
        
        // Parse JSON response
        const result = await response.json();
        console.log('✅ API Response:', result);
        
        // Kiểm tra error code maintenance
        if (result.error === 'SERVICE_MAINTENANCE') {
            console.warn('⚠️ Service maintenance detected');
            showMaintenancePage();
            return null;
        }
        
        return result;
        
    } catch (error) {
        console.error('❌ API Error:', error);
        
        // Nếu là lỗi network/connection (service down)
        if (error.message.includes('Failed to fetch') || 
            error.message.includes('NetworkError') ||
            error.name === 'TypeError') {
            console.error('🔴 Network error - showing maintenance page');
            showMaintenancePage();
            return null;
        }
        
        // Lỗi khác thì throw để xử lý ở nơi gọi
        throw error;
    }
}

/**
 * Hiển thị trang bảo trì
 */
function showMaintenancePage() {
    // Lưu URL hiện tại để restore sau khi reload
    sessionStorage.setItem('last_url', window.location.href);
    
    document.body.innerHTML = `
        <div id="maintenance-page" style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px;">
            <div style="background: white; padding: 60px 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; max-width: 500px; animation: fadeIn 0.5s ease;">
                <div style="font-size: 80px; margin-bottom: 20px; animation: pulse 2s infinite;">🔧</div>
                <h1 style="color: #333; font-size: 32px; margin-bottom: 15px; font-weight: 600;">Hệ Thống Đang Bảo Trì</h1>
                <p style="color: #666; font-size: 18px; line-height: 1.6; margin-bottom: 30px;">
                    Hệ thống cho thuê xe đang được nâng cấp để phục vụ bạn tốt hơn.<br>
                    Vui lòng quay lại sau ít phút.
                </p>
                <div style="display: inline-block; padding: 10px 20px; background: #f0f0f0; border-radius: 50px; color: #667eea; font-weight: 600; margin-bottom: 20px;">
                    ⏳ Service Unavailable
                </div>
                <br>
                <button onclick="location.reload()" style="margin-top: 10px; padding: 15px 40px; background: #667eea; color: white; border: none; border-radius: 50px; font-size: 16px; cursor: pointer; transition: all 0.3s; font-weight: 600;">
                    🔄 Thử lại
                </button>
                <p style="color: #999; font-size: 14px; margin-top: 30px;">
                    Nếu vấn đề vẫn tiếp diễn, vui lòng liên hệ hỗ trợ
                </p>
            </div>
        </div>
        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            #maintenance-page button:hover {
                background: #764ba2 !important;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
        </style>
    `;
}

/**
 * Show loading spinner
 */
function showLoading(message = 'Đang xử lý...') {
    // Xóa loader cũ nếu có
    hideLoading();
    
    const loader = document.createElement('div');
    loader.id = 'api-loader';
    loader.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; min-width: 200px;">
                <div style="border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                <p style="color: #333; margin: 0; font-weight: 500;">${message}</p>
            </div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    document.body.appendChild(loader);
}

/**
 * Hide loading spinner
 */
function hideLoading() {
    const loader = document.getElementById('api-loader');
    if (loader) {
        loader.remove();
    }
}

/**
 * Call API với loading indicator tự động
 * @param {string} endpoint 
 * @param {string} method 
 * @param {object|null} data 
 * @param {string} loadingMessage - Message hiển thị khi loading
 * @returns {Promise<object|null>}
 */
async function callAPIWithLoading(endpoint, method = 'GET', data = null, loadingMessage = 'Đang xử lý...') {
    showLoading(loadingMessage);
    try {
        const result = await callAPI(endpoint, method, data);
        return result;
    } finally {
        hideLoading();
    }
}

/**
 * Show success toast message
 */
function showSuccess(message, duration = 3000) {
    showToast(message, 'success', duration);
}

/**
 * Show error toast message
 */
function showError(message, duration = 3000) {
    showToast(message, 'error', duration);
}

/**
 * Show warning toast message
 */
function showWarning(message, duration = 3000) {
    showToast(message, 'warning', duration);
}

/**
 * Show info toast message
 */
function showInfo(message, duration = 3000) {
    showToast(message, 'info', duration);
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 350px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    `;
    
    toast.innerHTML = `
        <span style="font-size: 20px; font-weight: bold;">${icons[type] || icons.info}</span>
        <span>${message}</span>
    `;
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Log khi file được load
console.log('✅ API Handler loaded - Base URL:', API_BASE_URL);