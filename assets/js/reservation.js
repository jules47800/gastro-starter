/**
 * JavaScript pour le formulaire de réservation - Version 2.1 (Simplifiée)
 */
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    const reservationForm = document.getElementById("reservation-form");
    if (!reservationForm) return;

    const timeSelect = document.getElementById("time");
    const dateInput = document.getElementById("date");
    const peopleSelect = document.getElementById("people");
    const submitButton = reservationForm.querySelector('button[type="submit"]');
    const timeAvailabilityDiv = document.querySelector(".time-availability");
    const customerPhone = document.getElementById("customer_phone");
    const customerEmail = document.getElementById("customer_email");
    const customerName = document.getElementById("customer_name");

    let selectedTimeSlot = null; // Mémoriser le créneau sélectionné

    // Désactiver le bouton de soumission au départ
    if (submitButton) {
      submitButton.disabled = true;
    }

    // --- Gestion des paramètres URL (Pré-remplissage) ---
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    const urlDate = getUrlParameter('date');
    const urlTime = getUrlParameter('time');
    const urlPeople = getUrlParameter('people');

    if (urlPeople && peopleSelect) {
        peopleSelect.value = urlPeople;
    }

    if (urlTime) {
        selectedTimeSlot = urlTime; // Sera utilisé par updateAvailableTimes
    }

    if (urlDate && dateInput) {
        // On attend que flatpickr soit initialisé plus bas pour définir la date
        // Mais on définit la valeur de l'input pour l'instant
        dateInput.value = urlDate;
    }

    // --- Fonctions utilitaires ---
    function generateTimeSlots(startTime, endTime, interval = 15) {
      const slots = [];
      const start = new Date(`2000-01-01T${startTime}:00`);
      const end = new Date(`2000-01-01T${endTime}:00`);
      let current = new Date(start);
      while (current < end) {
        slots.push(current.toTimeString().slice(0, 5));
        current.setMinutes(current.getMinutes() + interval);
      }
      return slots;
    }

    function fetchReservations(date, people) {
      return new Promise((resolve) => {
        let apiUrl = `${
          gastro_starter_params.ajax_url
        }?action=gastro_starter_get_availability&date=${encodeURIComponent(
          date
        )}&_=${new Date().getTime()}`;
        
        // Ajouter le nombre de personnes si fourni
        if (people && people > 0) {
          apiUrl += `&people=${people}`;
        }
        
        fetch(apiUrl)
          .then((response) =>
            response.ok
              ? response.json()
              : Promise.reject("Network response was not ok.")
          )
          .then((data) => {
            if (data.success && data.data) {
              resolve(data.data);
            } else {
              resolve({ time_slots: {}, capacity_per_slot: 4 });
            }
          })
          .catch(() => resolve({ time_slots: {}, capacity_per_slot: 4 }));
      });
    }

    // --- Logique du formulaire ---
    function updateAvailableTimes() {
      const selectedDateInput = dateInput.value;
      const selectedPeople = parseInt(peopleSelect.value) || 0;
      const timeSection = document.getElementById('time-selection-section');

      // Cacher la section horaire si date OU personnes manquants
      if (!selectedDateInput || !selectedPeople || selectedPeople === 0) {
        if (timeSection) {
          timeSection.style.display = 'none';
        }
        timeSelect.disabled = true;
        
        // Message différent selon ce qui manque
        if (!selectedDateInput) {
          timeAvailabilityDiv.innerHTML = `<div class="info-state">${reservation_i18n.selectDate}</div>`;
        } else if (!selectedPeople || selectedPeople === 0) {
          timeAvailabilityDiv.innerHTML = `<div class="info-state">Veuillez sélectionner le nombre de personnes</div>`;
        }
        
        validateForm();
        return;
      }

      // Afficher la section horaire
      if (timeSection) {
        timeSection.style.display = 'block';
      }

      const dateParts = selectedDateInput.split("/");
      const selectedDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
      const dateObj = new Date(selectedDate);
      const dayKey = [
        "sunday",
        "monday",
        "tuesday",
        "wednesday",
        "thursday",
        "friday",
        "saturday",
      ][dateObj.getDay()];
      const daySchedule = (gastro_starter_params.daily_schedule || {})[dayKey];

      if (!daySchedule || !daySchedule.open) {
        timeSelect.disabled = true;
        timeAvailabilityDiv.innerHTML = `<div class="info-state">${reservation_i18n.restaurantClosed}</div>`;
        validateForm();
        return;
      }

      timeAvailabilityDiv.innerHTML = `<div class="loading-state">${reservation_i18n.checkingAvailability}</div>`;
      timeSelect.disabled = true;

      fetchReservations(selectedDate, selectedPeople).then((reservationsForDate) => {
        // CORRECTION : Utiliser les créneaux filtrés par le serveur
        const allSlots = reservationsForDate.available_slots.map(
          (slot) => slot.time
        );

        let availableTimesHtml = "";
        let hasAvailableSlots = false;
        
        // Vérifier si pooling est disponible pour ce groupe (en coulisses - invisible au client)
        const poolingInfo = reservationsForDate.pooling_data;

        timeSelect.innerHTML = `<option value="" disabled selected>${reservation_i18n.selectTime}</option>`;

        allSlots.forEach((timeSlot) => {
          const reservedSeats = reservationsForDate.time_slots[timeSlot] || 0;
          const availableSeats =
            reservationsForDate.capacity_per_slot - reservedSeats;
          const isSelectable = availableSeats >= selectedPeople;
          
          // Si pooling activé et c'est le créneau principal, le rendre sélectionnable (SILENCIEUSEMENT)
          const isPoolingSlot = poolingInfo && poolingInfo.available && 
                                poolingInfo.pooling_required && 
                                poolingInfo.primary_slot === timeSlot;

          if (isSelectable || isPoolingSlot) {
            hasAvailableSlots = true;
            // IMPORTANT: Affichage normal, pas de mention du pooling
            const displayText = `${timeSlot} (${isPoolingSlot ? selectedPeople : availableSeats} places)`;
            const option = new Option(displayText, timeSlot);
            timeSelect.appendChild(option);
          }

          availableTimesHtml += `
                        <div class="time-slot ${
                          isSelectable || isPoolingSlot ? "selectable" : "not-selectable"
                        }" data-time="${timeSlot}">
                            <span class="time-value">${timeSlot}</span>
                            <span class="availability-badge">${
                              isSelectable || isPoolingSlot
                                ? `${isPoolingSlot ? selectedPeople : availableSeats} ${reservation_i18n.available}`
                                : reservation_i18n.full
                            }</span>
                        </div>
                    `;
        });

        if (hasAvailableSlots) {
          timeAvailabilityDiv.innerHTML = availableTimesHtml;
          timeSelect.disabled = false;

          // Réappliquer la sélection mémorisée si elle est toujours valide
          if (
            selectedTimeSlot &&
            timeSelect.querySelector(`option[value="${selectedTimeSlot}"]`)
          ) {
            timeSelect.value = selectedTimeSlot;
            const activeSlot = timeAvailabilityDiv.querySelector(
              `[data-time="${selectedTimeSlot}"]`
            );
            if (activeSlot) activeSlot.classList.add("selected");
          }
        } else {
          const restaurantPhone =
            gastro_starter_params.restaurant_phone || "05 53 00 00 00";
          timeAvailabilityDiv.innerHTML = `<div class="info-state">
                        <p>${reservation_i18n.noOnlineBookingForGroup.replace(
                          "%d",
                          selectedPeople
                        )}</p>
                        <p>${reservation_i18n.callUs.replace(
                          "%s",
                          `<a href="tel:${restaurantPhone}" class="phone-link">${restaurantPhone}</a>`
                        )}</p>
                    </div>`;
          timeSelect.disabled = true;
        }

        validateForm();
      });
    }

    // Validation du numéro de téléphone
    function validatePhone(phone) {
      // Nettoyer le numéro en gardant seulement les chiffres et le +
      const cleanPhone = phone.replace(/[^\d+]/g, "");

      // Validation plus souple pour les numéros internationaux
      // Accepte : +33 6 12 34 56 78, +1 555 123 4567, 06 12 34 56 78, etc.
      const phoneRegex =
        /^(\+?\d{1,4}[\s-]?)?\(?\d{1,4}\)?[\s-]?\d{1,4}[\s-]?\d{1,4}[\s-]?\d{1,9}$/;

      // Vérifier que le numéro a au moins 8 chiffres (minimum international)
      const digitsOnly = cleanPhone.replace(/[^\d]/g, "");
      return phoneRegex.test(phone) && digitsOnly.length >= 8;
    }

    // Validation de l'email
    function validateEmail(email) {
      const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      return emailRegex.test(email);
    }

    // Validation du nom
    function validateName(name) {
      return name.length >= 2;
    }

    // Fonction pour mettre à jour les messages d'erreur
    function showFieldError(field, message) {
      let errorDiv = field.nextElementSibling;
      if (!errorDiv || !errorDiv.classList.contains("field-error")) {
        errorDiv = document.createElement("div");
        errorDiv.className = "field-error";
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
      }
      errorDiv.textContent = message;
      field.classList.add("error");
    }

    function clearFieldError(field) {
      const errorDiv = field.nextElementSibling;
      if (errorDiv && errorDiv.classList.contains("field-error")) {
        errorDiv.remove();
      }
      field.classList.remove("error");
    }

    // Validation en temps réel du téléphone
    customerPhone.addEventListener("input", function () {
      if (validatePhone(this.value)) {
        clearFieldError(this);
        this.classList.add('valid');
        this.classList.remove('error');
      } else {
        showFieldError(this, reservation_i18n.invalidPhone);
        this.classList.remove('valid');
      }
      validateForm();
    });

    // Validation en temps réel de l'email
    customerEmail.addEventListener("input", function () {
      if (validateEmail(this.value)) {
        clearFieldError(this);
        this.classList.add('valid');
        this.classList.remove('error');
      } else {
        showFieldError(this, reservation_i18n.invalidEmail);
        this.classList.remove('valid');
      }
      validateForm();
    });

    // Validation en temps réel du nom
    customerName.addEventListener("input", function () {
      if (validateName(this.value)) {
        clearFieldError(this);
        this.classList.add('valid');
        this.classList.remove('error');
      } else {
        showFieldError(this, reservation_i18n.invalidName);
        this.classList.remove('valid');
      }
      validateForm();
    });

    // Validation du formulaire
    function validateForm() {
      if (!submitButton) return;

      const requiredFields = reservationForm.querySelectorAll("[required]");
      let isValid = true;

      requiredFields.forEach((field) => {
        if (field.type === "checkbox") {
          if (!field.checked) isValid = false;
        } else {
          if (!field.value || field.disabled) isValid = false;

          // Validations spécifiques
          switch (field.id) {
            case "customer_phone":
              if (!validatePhone(field.value)) isValid = false;
              break;
            case "customer_email":
              if (!validateEmail(field.value)) isValid = false;
              break;
            case "customer_name":
              if (!validateName(field.value)) isValid = false;
              break;
          }
        }
      });

      submitButton.disabled = !isValid;
    }

    // Ajouter du style pour les erreurs
    const style = document.createElement("style");
    style.textContent = `
            .field-error {
                color: #dc3545;
                font-size: 0.875em;
                margin-top: 4px;
            }
            input.error {
                border-color: #dc3545;
            }
            input.error:focus {
                box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
            }
        `;
    document.head.appendChild(style);

    // --- Événements ---
    dateInput.addEventListener("change", updateAvailableTimes);
    peopleSelect.addEventListener("change", updateAvailableTimes);

    timeSelect.addEventListener("change", () => {
      selectedTimeSlot = timeSelect.value;
      // Mettre en surbrillance le créneau cliquable correspondant
      document
        .querySelectorAll(".time-slot")
        .forEach((slot) => slot.classList.remove("selected"));
      const activeSlot = timeAvailabilityDiv.querySelector(
        `[data-time="${selectedTimeSlot}"]`
      );
      if (activeSlot) activeSlot.classList.add("selected");
      validateForm();
    });

    // Rendre les créneaux horaires cliquables
    timeAvailabilityDiv.addEventListener("click", function (e) {
      const target = e.target.closest(".time-slot.selectable");
      if (target) {
        const time = target.dataset.time;
        timeSelect.value = time;
        // Déclencher l'événement 'change' pour que la validation se fasse
        timeSelect.dispatchEvent(new Event("change"));
      }
    });

    // Validation initiale
    reservationForm.addEventListener("input", validateForm);
    validateForm();

    // ── Helpers pour les événements du calendrier ─────────────────
    const eventDates = (gastro_starter_params.event_dates) || {};

    function fpDateToISO(date) {
        return date.getFullYear() + '-'
            + ('0' + (date.getMonth() + 1)).slice(-2) + '-'
            + ('0' + date.getDate()).slice(-2);
    }

    function showEventBanner(isoDate) {
        var banner = document.getElementById('reservation-event-banner');
        if (!banner) return;
        var ev = eventDates[isoDate];
        if (!ev) {
            banner.style.display = 'none';
            return;
        }
        var timeStr = '';
        if (ev.time) {
            var parts = ev.time.split(':');
            timeStr = ' · ' + parts[0] + 'h' + (parts[1] !== '00' ? parts[1] : '');
        }
        var statusClass = ev.status === 'full' ? 'event-banner--full' : '';
        var statusLabel = ev.status === 'full'
            ? '<span class="event-banner__status">Complet</span>'
            : '';
        var label = ev.subtitle ? ev.subtitle : 'Soirée Bec Fin';
        banner.className = 'reservation-event-banner ' + statusClass;
        banner.innerHTML =
            '<div class="event-banner__icon">★</div>'
            + '<div class="event-banner__body">'
            +   '<span class="event-banner__label">' + label + '</span>'
            +   '<strong class="event-banner__title">' + ev.title + '</strong>'
            +   '<span class="event-banner__meta">' + timeStr.replace(' · ', '') + statusLabel + '</span>'
            + '</div>'
            + (ev.status !== 'full'
                ? '<a href="' + ev.url + '" class="event-banner__link" target="_blank">Voir l\'événement →</a>'
                : '');
        banner.style.display = 'flex';
    }

    // Initialisation de Flatpickr
    if (typeof flatpickr !== "undefined") {
      const holidayString = gastro_starter_params.holiday_dates || "";
      const holidayDates = holidayString
        ? holidayString
            .split(",")
            .map((d) => d.trim())
            .filter(Boolean)
        : [];

      const bookingPeriod = parseInt(gastro_starter_params.booking_period) || 1;
      const maxDate = new Date();
      maxDate.setMonth(maxDate.getMonth() + bookingPeriod);

      flatpickr(dateInput, {
        dateFormat: "d/m/Y",
        minDate: "today",
        maxDate: maxDate,
        locale: reservation_i18n.currentLocale,
        defaultDate: urlDate || null, // Utiliser la date de l'URL si présente
        disable: [
          function (date) {
            // Règle 1: Jours de fermeture hebdomadaire
            const dayKey = [
              "sunday",
              "monday",
              "tuesday",
              "wednesday",
              "thursday",
              "friday",
              "saturday",
            ][date.getDay()];
            const schedule = (gastro_starter_params.daily_schedule || {})[dayKey];
            if (!schedule || !schedule.open) {
              return true; // Désactiver si fermé
            }

            // Règle 2: Jours de vacances
            const dateString =
              date.getFullYear() +
              "-" +
              ("0" + (date.getMonth() + 1)).slice(-2) +
              "-" +
              ("0" + date.getDate()).slice(-2);
            if (holidayDates.includes(dateString)) {
              return true; // Désactiver si c'est un jour de vacances
            }

            return false; // Garder la date activée
          },
        ],
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            var iso = fpDateToISO(dayElem.dateObj);
            if (eventDates[iso]) {
                var dot = document.createElement('span');
                dot.className = 'flatpickr-event-dot'
                    + (eventDates[iso].status === 'full' ? ' flatpickr-event-dot--full' : '');
                dayElem.appendChild(dot);
                dayElem.classList.add('has-event');
            }
        },
        onChange: function (selectedDates, dateStr, instance) {
          selectedTimeSlot = null;
          if (urlDate && dateStr === urlDate && urlTime) {
             selectedTimeSlot = urlTime;
          }
          timeSelect.value = "";
          updateAvailableTimes();
          // Bannière événement
          if (selectedDates.length > 0) {
              showEventBanner(fpDateToISO(selectedDates[0]));
          } else {
              var b = document.getElementById('reservation-event-banner');
              if (b) b.style.display = 'none';
          }
        },
        onReady: function(selectedDates, dateStr, instance) {
            if (dateStr) {
                updateAvailableTimes();
            }
            if (selectedDates.length > 0) {
                showEventBanner(fpDateToISO(selectedDates[0]));
            }
        }
      });
    }
  });
})();
