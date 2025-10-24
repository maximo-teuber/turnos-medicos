// Configuración de horarios por médico
const horariosMedicos = {
  clinico: {
    diasHabiles: [1, 2, 3, 4, 5], // Lunes a Viernes
    minTime: "09:00",
    maxTime: "13:00"
  },
  pediatra: {
    diasHabiles: [1, 3, 5], // Lunes, Miércoles y Viernes
    minTime: "10:00",
    maxTime: "14:00"
  },
  cardiologo: {
    diasHabiles: [1, 2, 3, 4, 5], // Lunes a Viernes
    minTime: "08:00",
    maxTime: "12:00"
  }
};

// Inicializar Flatpickr
let calendario = flatpickr("#calendario", {
  locale: "es",
  dateFormat: "d-m-Y",
  minDate: "today",
  static: true,
  onChange: function(selectedDates) {
    if (selectedDates.length > 0) {
      mostrarHorarios();
    }
  }
});

const medicoSelect = document.getElementById("medicoSelect");
const horariosDiv = document.getElementById("horarios");
const turnoConfirmado = document.getElementById("turnoConfirmado");
const listaTurnos = document.getElementById("listaTurnos");

// Cuando se cambia de médico
medicoSelect.addEventListener("change", function () {
  horariosDiv.innerHTML = "";
  calendario.clear();
  turnoConfirmado.textContent = "";
});

// Generar horarios disponibles según el médico y la fecha
function mostrarHorarios() {
  horariosDiv.innerHTML = "";
  turnoConfirmado.textContent = "";

  const medico = medicoSelect.value;
  if (!medico) return;

  const config = horariosMedicos[medico];
  const minHour = parseInt(config.minTime.split(":")[0]);
  const maxHour = parseInt(config.maxTime.split(":")[0]);

  for (let h = minHour; h < maxHour; h++) {
    const hora = (h < 10 ? "0" + h : h) + ":00";
    const btn = document.createElement("button");
    btn.textContent = hora;
    btn.classList.add("horario-btn");

    btn.addEventListener("click", () => {
      // Quitar selección previa
      document.querySelectorAll(".horario-btn").forEach(b => b.classList.remove("selected"));
      btn.classList.add("selected");

      // Obtener fecha elegida y nombre del médico
      const fecha = calendario.input.value;
      const medicoTexto = medicoSelect.options[medicoSelect.selectedIndex].text;

      // Mensaje de confirmación
      turnoConfirmado.textContent = `✅ Turno reservado con ${medicoTexto} el ${fecha} a las ${hora}`;

      // Agregar a la lista de turnos reservados
      const li = document.createElement("li");
      li.textContent = `${medicoTexto} - ${fecha} a las ${hora}`;
      listaTurnos.appendChild(li);
    });

    horariosDiv.appendChild(btn);
  }
}
