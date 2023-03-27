
const toggle = {

	/** @type {Object<string, HTMLElement>} */
	active: {},

	/** @type {HTMLElement} */
	stickyBar: null,

	init() {
		let buttons = document.querySelectorAll(`[toggle-id]`);

		for (let button of buttons) {
			let name = button.getAttribute("toggle-name");
			button.addEventListener("click", () => this.activate(button, name));

			if (button.getAttribute("toggle-default"))
				this.activate(button, name);
		}
	},

	insertAfter(ref, node) {
		ref.parentNode.insertBefore(node, ref.nextSibling);
	},

	/**
	 * Activate toggle based on button node.
	 * @param {HTMLElement}	node
	 * @param {String}		name
	 */
	activate(node, name = null) {
		let id = node.getAttribute("toggle-id");
		let target = document.querySelector(`[toggle-target="${id}"]`);
		let activated = node.classList.contains("active");

		if (!name) {
			if (activated) {
				target.classList.remove("active");
				node.classList.remove("active");
			} else {
				target.classList.add("active");
				node.classList.add("active");
			}

			return;
		}

		let active = this.active[name];
		if (node === active)
			return;

		if (active) {
			let id = active.getAttribute("toggle-id");
			document.querySelector(`[toggle-target="${id}"]`).classList.remove("active");
			active.classList.remove("active");
		}

		node.classList.add("active");
		target.classList.add("active");

		if (name === "stacktrace") {
			if (!this.stickyBar) {
				this.stickyBar = document.createElement("div");
				this.stickyBar.classList.add("sticky-bar");
			}

			this.insertAfter(node, this.stickyBar);
		}

		this.active[name] = node;
	}
}

const error = {
	init() {
		toggle.init();

		if (window.sticker) {
			const handler = () => {
				window.sticker.play();
				document.body.removeEventListener("click", handler);
			};

			document.body.addEventListener("click", handler);
		}
	},

}

error.init();
