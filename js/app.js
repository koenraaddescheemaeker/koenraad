const form = document.getElementById("chat-form");
const input = document.getElementById("message-input");
const messages = document.getElementById("messages");

const conversation = [];
let waitingForReply = false;

form.addEventListener("submit", async function (event) {
    event.preventDefault();

    const text = input.value.trim();

    if (!text || waitingForReply) {
        return;
    }

    addMessage(text, "user");
    conversation.push({
        role: "user",
        content: text
    });

    input.value = "";
    input.focus();

    waitingForReply = true;
    input.disabled = true;

    const button = form.querySelector("button");
    button.disabled = true;
    button.textContent = "Even denken…";

    try {
        const response = await fetch("api/chat.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                messages: conversation
            })
        });

        const rawResponse = await response.text();
        let data;

        try {
            data = JSON.parse(rawResponse);
        } catch {
            throw new Error(
                `Backend gaf geen geldige JSON terug (HTTP ${response.status}): ${rawResponse.slice(0, 500)}`
            );
        }

        if (!response.ok || !data.success || !data.message) {
            throw new Error(
                `Backendfout (HTTP ${response.status}): ${data.error || "Onbekende fout"}`
            );
        }

        addMessage(data.message, "koenraad");

        conversation.push({
            role: "assistant",
            content: data.message
        });
    } catch (error) {
        addMessage(
            "Sorry, ik kan momenteel geen antwoord geven. Probeer het gerust opnieuw.",
            "koenraad"
        );

        console.error("Koenraad-chatfout:", error);
        addMessage(error.message, "koenraad");
    } finally {
        waitingForReply = false;
        input.disabled = false;
        button.disabled = false;
        button.textContent = "Verstuur";
        input.focus();
    }
});

function addMessage(text, type) {
    const message = document.createElement("div");
    message.className = `message ${type}`;

    if (type === "koenraad") {
        const name = document.createElement("div");
        name.className = "message-name";
        name.textContent = "Koenraad";
        message.appendChild(name);

        const content = document.createElement("div");
        content.className = "message-content";

        // Verwijder eventuele escape-tekens rond Markdown-opmaak.
        const lines = String(text)
            .replace(/\\([*_#])/g, "$1")
            .split(/\r?\n/);

        let paragraph = null;
        let list = null;
        let listType = null;

        function addInlineText(parent, value) {
            const parts = value.split(/(\*\*.*?\*\*)/g);

            parts.forEach(part => {
                if (part.startsWith("**") && part.endsWith("**") && part.length > 4) {
                    const strong = document.createElement("strong");
                    strong.textContent = part.slice(2, -2);
                    parent.appendChild(strong);
                } else if (part) {
                    parent.appendChild(document.createTextNode(part));
                }
            });
        }

        function finishParagraph() {
            if (paragraph) {
                content.appendChild(paragraph);
                paragraph = null;
            }
        }

        function finishList() {
            if (list) {
                content.appendChild(list);
                list = null;
                listType = null;
            }
        }

        lines.forEach(line => {
            const trimmed = line.trim();
            const bullet = trimmed.match(/^[-*]\s+(.+)$/);
            const numbered = trimmed.match(/^\d+[.)]\s+(.+)$/);
            const item = bullet || numbered;
            const nextListType = bullet ? "ul" : numbered ? "ol" : null;

            if (!trimmed) {
                finishParagraph();
                finishList();
                return;
            }

            if (item) {
                finishParagraph();

                if (listType !== nextListType) {
                    finishList();
                    list = document.createElement(nextListType);
                    listType = nextListType;
                }

                const li = document.createElement("li");
                addInlineText(li, item[1]);
                list.appendChild(li);
                return;
            }

            finishList();

            // Maak kopjes leesbaar zonder Markdown-tekens.
            const heading = trimmed.match(/^#{1,3}\s+(.+)$/);
            if (heading) {
                finishParagraph();
                const h = document.createElement("h3");
                addInlineText(h, heading[1]);
                content.appendChild(h);
                return;
            }

            if (!paragraph) {
                paragraph = document.createElement("p");
            } else {
                paragraph.appendChild(document.createTextNode(" "));
            }

            addInlineText(paragraph, trimmed);
        });

        finishParagraph();
        finishList();
        message.appendChild(content);
    } else {
        message.textContent = text;
    }

    messages.appendChild(message);
    messages.scrollTop = messages.scrollHeight;
}
