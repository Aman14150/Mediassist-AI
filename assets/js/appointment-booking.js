(function () {
  "use strict";

  const form = document.querySelector("#appointment-booking-form");
  if (!form) return;

  const departmentSelect = form.querySelector("#booking-department");
  const doctorSelect = form.querySelector("#booking-doctor");
  const dateInput = form.querySelector("#booking-date");
  const timeSelect = form.querySelector("#booking-time");
  const confirmation = form.querySelector("#booking-confirmed");
  const feeLabel = form.querySelector("#booking-fee");
  const status = form.querySelector("#booking-status");
  const submitButton = form.querySelector("button[type='submit']");
  let departments = [];
  let slotTokens = new Map();

  const localDate = new Date();
  localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
  dateInput.min = localDate.toISOString().slice(0, 10);

  function showStatus(message, type) {
    status.textContent = message;
    status.className = `alert alert-${type}`;
  }

  function resetTimes(message = "Select doctor and date first") {
    slotTokens = new Map();
    timeSelect.innerHTML = `<option value="">${message}</option>`;
    timeSelect.disabled = true;
    confirmation.checked = false;
  }

  async function request(url, options = {}) {
    const response = await fetch(url, options);
    const body = await response.json().catch(() => ({ message: "The server returned an invalid response." }));
    if (!response.ok || body.ok === false) throw new Error(body.message || "The request could not be completed.");
    return body;
  }

  async function loadDepartments() {
    try {
      const body = await request("api/public/departments.php");
      departments = body.departments;
      departmentSelect.innerHTML = '<option value="">Select Department</option>' + departments.map((item) =>
        `<option value="${item.slug}">${item.name}</option>`
      ).join("");
    } catch (error) {
      departmentSelect.innerHTML = '<option value="">Departments unavailable</option>';
      departmentSelect.disabled = true;
      showStatus(error.message, "danger");
    }
  }

  departmentSelect.addEventListener("change", function () {
    const department = departments.find((item) => item.slug === this.value);
    doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
    if (department) {
      feeLabel.textContent = `Rs. ${department.consultation_fee_inr} consultation fee`;
      department.doctors.forEach((doctor) => {
        const option = document.createElement("option");
        option.value = doctor.id;
        option.textContent = doctor.specialty_note ? `${doctor.name} — ${doctor.specialty_note}` : doctor.name;
        doctorSelect.appendChild(option);
      });
      doctorSelect.disabled = false;
    } else {
      feeLabel.textContent = "consultation fee shown after selecting a department";
      doctorSelect.disabled = true;
    }
    resetTimes();
  });

  async function loadSlots() {
    resetTimes("Checking availability…");
    if (!departmentSelect.value || !doctorSelect.value || !dateInput.value) return;
    try {
      const body = await request("api/public/check-availability.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ department: departmentSelect.value, doctor: doctorSelect.value, date: dateInput.value })
      });
      const slots = body.doctors[0]?.slots || [];
      if (!slots.length) {
        resetTimes("No slots available on this date");
        return;
      }
      timeSelect.innerHTML = '<option value="">Select Time</option>';
      slots.forEach((slot) => {
        slotTokens.set(slot.time, slot.confirmation_token);
        const option = document.createElement("option");
        option.value = slot.time;
        option.textContent = slot.display_time;
        timeSelect.appendChild(option);
      });
      timeSelect.disabled = false;
    } catch (error) {
      resetTimes("Unable to load slots");
      showStatus(error.message, "danger");
    }
  }

  doctorSelect.addEventListener("change", loadSlots);
  dateInput.addEventListener("change", loadSlots);
  timeSelect.addEventListener("change", () => { confirmation.checked = false; });

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const data = new FormData(form);
    const token = slotTokens.get(data.get("time"));
    if (!token) {
      showStatus("Please check availability and select a time again.", "warning");
      return;
    }
    submitButton.disabled = true;
    showStatus("Confirming your appointment…", "info");
    try {
      const body = await request("api/public/create-appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          patient_name: data.get("name"), phone: data.get("phone"), email: data.get("email"),
          department: data.get("department"), doctor: data.get("doctor"), date: data.get("date"),
          time: data.get("time"), notes: data.get("message"), confirmed: confirmation.checked,
          confirmation_token: token
        })
      });
      showStatus(`${body.message} Please save reference ${body.appointment.booking_reference}.`, "success");
      form.reset();
      doctorSelect.disabled = true;
      resetTimes();
    } catch (error) {
      showStatus(error.message, "danger");
      await loadSlots();
    } finally {
      submitButton.disabled = false;
    }
  });

  loadDepartments();
})();
