
$(document).ready(() => {
    // Обработка формы
    $('form').on('submit', function(event) {
        event.preventDefault();
        var $form = $(this);
        var $inputs = $form.find('input, button, textarea, select');
        var formData = new FormData($form[0]);
        
        // Валидация на клиенте (можно добавить более сложную логику)
        let isValid = true;
        $form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('error-field');
            } else {
                $(this).removeClass('error-field');
            }
        });
        
        if (!isValid) {
            alert('Пожалуйста, заполните все обязательные поля');
            return;
        }
        
        $inputs.prop('disabled', true);
        
        // Определяем метод (POST для новых, PUT для существующих)
        const method = $form.hasClass('edit-form') ? 'PUT' : 'POST';
        
        $.ajax({
            url: 'api.php',
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    if (method === 'POST') {
                        // Показываем данные нового пользователя
                        alert(`Ваши данные сохранены!\nЛогин: ${response.data.login}\nПароль: ${response.data.password}`);
                        window.location.href = response.data.profile_url;
                    } else {
                        alert('Данные успешно обновлены');
                        window.location.reload();
                    }
                } else {
                    // Показываем ошибки валидации
                    let errorMsg = 'Ошибка при отправке формы:\n';
                    for (const field in response.errors) {
                        errorMsg += `${response.errors[field]}\n`;
                        $(`[name="${field}"]`).addClass('error-field');
                    }
                    alert(errorMsg);
                }
            },
            error: function(xhr) {
                alert('Произошла ошибка при отправке формы');
            },
            complete: function() {
                $inputs.prop('disabled', false);
            }
        });
    });



    const $navMenu = $("#mobileMenu");
    $("#navbar-toggle").click(function(event){
        $navMenu.toggle("slide", { direction: "down" }, 600)
    });
    const updateSliderCounter =(current, total)=>{
        current = current < 10 ? "0" + current : current;
        total = total < 10 ? "0" + total : total;
        $(".slick-review-counter").text(current+"/"+total)

    };

    const $slickReview = $("#slick-review")

    $slickReview.on("init", function (ev, slick){
        updateSliderCounter(slick.slickCurrentSlide() + 1, slick.slideCount);
    })


    $slickReview.slick({
        dots: false,
        prevArrow: '',
        nextArrow: '',
    })

    $slickReview.on('afterChange', function (ev, slick){
        updateSliderCounter(slick.slickCurrentSlide()+1, slick.slideCount);
    })

    $(".review-arrow-prev").click(function (){
        $("#slick-review").slick("slickPrev")
    })
    $(".review-arrow-next").click(function (){
        $("#slick-review").slick("slickNext")
    })
    $('.panel-btn').click(function (){
        const t = $(this).parents(".panel");
        t.toggleClass("panel_open")
        t.toggleClass("panel_close")
        t.children(".panel-body").slideToggle(400)
    })
    $('#slick-customers-first').slick({
        infinite: true,
        speed: 600,
        autoplay: true,
        autoplaySpeed: 2000,
        nextArrow: ``,
        prevArrow: ``,
        slidesToShow: 6,
        slidesToScroll: 1,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 5,
                    slidesToScroll: 1,
                    infinite: true,
                }
            },
            {
                breakpoint: 600,
                settings: {
                    slidesToShow: 4,
                    slidesToScroll: 2
                }
            },
            {
                breakpoint: 480,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1
                }
            }
            // You can unslick at a given breakpoint now by adding:
            // settings: "unslick"
            // instead of a settings object
        ]
    });
    $('#slick-customers-second').slick({
        infinite: true,
        speed: 600,
        autoplay: true,
        autoplaySpeed: 3250,
        nextArrow: ``,
        prevArrow: ``,
        slidesToShow: 6,
        slidesToScroll: 1,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 5,
                    slidesToScroll: 1,
                    infinite: true,
                }
            },
            {
                breakpoint: 600,
                settings: {
                    slidesToShow: 4,
                    slidesToScroll: 2
                }
            },
            {
                breakpoint: 480,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1
                }
            }
        ]
    });



})

