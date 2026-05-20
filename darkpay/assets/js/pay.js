// pay.js - DarkPay secure gateway logic & animations

let currentMethod = 'upi';

// Switch payment method tabs
function switchTab(method) {
    currentMethod = method;
    
    // Toggle active tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.trim().toLowerCase() === method) {
            btn.classList.add('active');
        }
    });
    
    // Toggle active panels
    document.querySelectorAll('.payment-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById(`panel_${method}`).classList.add('active');
    
    // Update hidden inputs and required tags
    document.getElementById('selected_method').value = method;
    
    const cardNum = document.getElementById('card_num_input');
    const cardName = document.getElementById('card_name_input');
    const cardExp = document.getElementById('card_expiry_input');
    const cardCvv = document.getElementById('card_cvv_input');
    const upiId = document.getElementById('upi_id_input');
    const bankCode = document.getElementById('selected_bank_code');
    
    if (method === 'card') {
        cardNum.required = true;
        cardName.required = true;
        cardExp.required = true;
        cardCvv.required = true;
        upiId.required = false;
        bankCode.required = false;
    } else if (method === 'upi') {
        cardNum.required = false;
        cardName.required = false;
        cardExp.required = false;
        cardCvv.required = false;
        upiId.required = true;
        bankCode.required = false;
    } else if (method === 'netbank') {
        cardNum.required = false;
        cardName.required = false;
        cardExp.required = false;
        cardCvv.required = false;
        upiId.required = false;
        bankCode.required = true;
    }
}

// Auto space card number input (4-4-4-4)
function formatCardNumber(input) {
    let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) {
            formatted += ' ';
        }
        formatted += value[i];
    }
    input.value = formatted;
}

// Auto format card expiry (MM/YY)
function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 2) {
        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
    } else {
        input.value = value;
    }
}

// Update the 2D graphic card element
function updateCardGraphic() {
    const numInput = document.getElementById('card_num_input').value;
    const nameInput = document.getElementById('card_name_input').value;
    const expInput = document.getElementById('card_expiry_input').value;
    
    const numDisplay = document.getElementById('card_num_display');
    const nameDisplay = document.getElementById('card_name_display');
    const expDisplay = document.getElementById('card_expiry_display');
    const cardLogo = document.getElementById('card_logo');
    const inlineIcon = document.getElementById('inline_brand_icon');
    
    // Number display
    numDisplay.innerText = numInput ? numInput : '•••• •••• •••• ••••';
    
    // Name display
    nameDisplay.innerText = nameInput ? nameInput : 'YOUR NAME';
    
    // Expiry display
    expDisplay.innerText = expInput ? expInput : 'MM/YY';
    
    // Brand detection
    const cleanNum = numInput.replace(/\s+/g, '');
    if (cleanNum.startsWith('4')) {
        cardLogo.innerText = 'VISA';
        cardLogo.style.color = '#00579f';
        inlineIcon.innerText = '💳 Visa';
    } else if (cleanNum.startsWith('5')) {
        cardLogo.innerText = 'MC';
        cardLogo.style.color = '#ff5f00';
        inlineIcon.innerText = '💳 Mastercard';
    } else if (cleanNum.startsWith('3')) {
        cardLogo.innerText = 'AMEX';
        cardLogo.style.color = '#016fd0';
        inlineIcon.innerText = '💳 Amex';
    } else if (cleanNum.startsWith('6')) {
        cardLogo.innerText = 'DISC';
        cardLogo.style.color = '#f9a01b';
        inlineIcon.innerText = '💳 Discover';
    } else {
        cardLogo.innerText = 'DarkPay';
        cardLogo.style.color = '#fff';
        inlineIcon.innerText = '💳';
    }
}

// Select quick UPI shortcuts
function selectUpiShortcut(id) {
    const input = document.getElementById('upi_id_input');
    input.value = id;
}

// Select netbanking bank cards
function selectBank(code, name) {
    document.getElementById('selected_bank_code').value = code;
    document.getElementById('bank_selected_text').innerText = `Selected: ${name}`;
    
    document.querySelectorAll('.bank-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
}

// Simulated Multi-Step processing animation
function handlePaymentSubmit(e) {
    e.preventDefault();
    
    // Validation for netbanking
    if (currentMethod === 'netbank' && !document.getElementById('selected_bank_code').value) {
        alert('Please select a bank option to proceed.');
        return;
    }
    
    const modal = document.getElementById('processing_modal');
    modal.classList.add('active');
    
    const mTitle = document.getElementById('modal_title');
    const mSubtitle = document.getElementById('modal_subtitle');
    const spinner = document.querySelector('.glow-spinner');
    const checkmark = document.getElementById('modal_checkmark');
    
    const step1 = document.getElementById('step_1');
    const step2 = document.getElementById('step_2');
    const step3 = document.getElementById('step_3');
    
    // Step 1: Handshake
    setTimeout(() => {
        step1.classList.add('complete');
        step2.classList.add('active');
        mTitle.innerText = "Recording UPI Reference...";
        mSubtitle.innerText = "Saving your UPI reference while waiting for provider-side payment confirmation.";
        
        // Step 2: Auth Verification
        setTimeout(() => {
            step2.classList.add('complete');
            step3.classList.add('active');
            mTitle.innerText = "Checking Provider Status...";
            mSubtitle.innerText = "DarkPay will approve this order only after a trusted gateway webhook confirms payment.";
            
            // Step 3: Confirmation
            setTimeout(() => {
                step3.classList.add('complete');
                spinner.style.display = 'none';
                checkmark.style.display = 'block';
                mTitle.innerText = "Status Check Saved";
                mSubtitle.innerText = "If the provider has confirmed payment, this will unlock automatically. Otherwise it stays pending safely.";
                
                // Submit Form
                setTimeout(() => {
                    document.getElementById('payment_form').submit();
                }, 1500);
                
            }, 1200);
            
        }, 1200);
        
    }, 1200);
}

async function pollRealtimeStatus() {
    const form = document.getElementById('payment_form');
    const statusBox = document.getElementById('realtime_status');
    if (!form || !statusBox || !form.dataset.statusUrl) return;

    try {
        const res = await fetch(form.dataset.statusUrl, { cache: 'no-store' });
        const data = await res.json();
        if (data.ok && data.status === 'verified') {
            statusBox.innerText = "Payment verified automatically. Redirecting...";
            statusBox.classList.add('verified');
            window.location.href = form.dataset.successUrl || '../store/success.php?multiple=true';
            return;
        }
        if (data.ok && data.customer_upi_ref) {
            statusBox.innerText = "UPI reference received. Waiting for provider confirmation.";
        }
    } catch (err) {
        statusBox.innerText = "Live status temporarily unavailable. Your payment reference is still safe.";
    }

    setTimeout(pollRealtimeStatus, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    switchTab('upi');
    pollRealtimeStatus();
});
