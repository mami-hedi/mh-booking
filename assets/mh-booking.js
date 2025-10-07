(function($){
$(document).ready(function(){
    let currentStep = 1;

    function showStep(step){
        $('.mh-step').hide();
        $('.mh-step[data-step="'+step+'"]').fadeIn();
    }

    function initCalendar(){
        const type_id = $('#mh-type').val();
        if(!type_id) return;

        const calendarEl = document.getElementById('mh-calendar');
        if(!calendarEl) return;

        if(window.mhCalendar) window.mhCalendar.destroy();

        window.mhCalendar = new FullCalendar.Calendar(calendarEl,{
            initialView:'timeGridWeek',
            headerToolbar:{
                left:'prev,next today',
                center:'title',
                right:'dayGridMonth,timeGridWeek,timeGridDay'
            },
            selectable:true,
            nowIndicator:true,
            slotMinTime:"08:00:00",
            slotMaxTime:"18:00:00",
            height:500,
            timeZone:'local',
            events:function(fetchInfo,successCallback){
                $.post(mhBookingAjax.ajax_url,{
                    action:'mh_booking_get_events',
                    nonce:mhBookingAjax.nonce,
                    type_id:type_id
                },function(resp){
                    successCallback(resp);
                });
            },
            // Utilisation de dateClick pour capturer le clic sur un créneau
            dateClick: function(info){
                // Vérifier si le créneau est réservé
                let event = window.mhCalendar.getEvents().find(e=>e.startStr===info.dateStr && e.title==='Réservé');
                if(event) return; // ne rien faire si réservé

                // Mettre à jour le champ caché
                $('#mh-datetime').val(info.dateStr);

                // Affichage visuel pour l'utilisateur
                const date = new Date(info.dateStr);
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour:'2-digit', minute:'2-digit' };
                $('#mh-selected-datetime').text("Date sélectionnée : " + date.toLocaleDateString('fr-FR', options));
            },
            eventDidMount: function(info){
                if(info.event.title==='Réservé'){
                    info.el.style.backgroundColor='#ff4d4d';
                    info.el.style.color='#fff';
                    info.el.style.cursor='not-allowed';
                    info.el.title='Ce créneau est déjà réservé';
                } else {
                    info.el.style.cursor='pointer';
                    info.el.title='Disponible';
                }
            }
        });

        window.mhCalendar.render();
    }

    // Boutons suivant / précédent
    $('.mh-next').click(function(){
        if(currentStep===1) initCalendar();
        currentStep++;
        showStep(currentStep);
    });

    $('.mh-prev').click(function(){ 
        currentStep--; 
        showStep(currentStep); 
    });

    // Soumission formulaire
    $('#mh-booking-form').submit(function(e){
        e.preventDefault();
        const $resp = $('#mh-booking-response');
        $resp.text('Envoi...');
        $.post(mhBookingAjax.ajax_url, $(this).serialize()+'&nonce='+mhBookingAjax.nonce+'&action=mh_booking_submit', function(resp){
            if(resp.success){ 
                $resp.text(resp.data); 
                $('#mh-booking-form')[0].reset(); 
                currentStep=1; 
                showStep(currentStep); 
                if(window.mhCalendar) window.mhCalendar.refetchEvents(); 
            } else $resp.text(resp.data);
        });
    });

    showStep(currentStep);
});
})(jQuery);
