const form = document.getElementById("chat-form");
const input = document.getElementById("message-input");
const messages = document.getElementById("messages");

form.addEventListener("submit", function (event) {
    event.preventDefault();

    const text = input.value.trim();

    if (!text) {
        return;
    }

    const message = document.createElement("div");

    message.className = "message user";
    message.textContent = text;

    messages.appendChild(message);

    input.value = "";
    input.focus();

    messages.scrollTop = messages.scrollHeight;
});
