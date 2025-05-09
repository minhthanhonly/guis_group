document.addEventListener('DOMContentLoaded', function() {
    const checkin = document.getElementById('checkin');
    const checkout = document.getElementById('checkout');

    if(checkin) {
        checkin.addEventListener('click', function() {
            doCheckin(checkin, checkout);
        });
    }

    if(checkout) {
        checkout.addEventListener('click', function() {
            doCheckout(checkout);
        });
    }
});

function showLoading(button) {
    $(button).prepend('<span class="spinner-grow me-1" role="status" aria-hidden="true"></span>');
    $(button).attr('disabled', true);
}

function hideLoading(button) {
    $(button).find('.spinner-grow').remove();
}

function doCheckin(button, checkout) {
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkin',
        data: {
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                
                if(response.data.timecard_id) {
                    $(checkout).attr('data-id', response.data.timecard_id);
                    $(checkout).attr('data-open', response.data.timecard_open);
                    $(checkout).attr('disabled', false);
                }
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}

function doCheckout(button) {
    const id = button.getAttribute('data-id');
    const open = button.getAttribute('data-open');
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkout',
        data: {
            id: id,
            open: open,
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                $(button).attr('disabled', true);

                let result = '';

                if(response.data.timecard_time) {
                    result = 'お疲れ様でした！<br>勤務時間は' + response.data.timecard_time + 'です。';
                }

                if(response.data.timecard_timeover && response.data.timecard_timeover != '0:00') {
                    result += '<br>時間外は' + response.data.timecard_timeover + 'です。';
                }
                const $p = $(`<p class="text-success mb-0">${result}</p>`);
                $('#timecard-result').html($p);
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}
  