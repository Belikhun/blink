
const toggle = {

	/** @type {Object<string, HTMLElement>} */
	active: {},

	init() {
		let buttons = document.querySelectorAll(`[toggle-id]`);

		for (let button of buttons) {
			let name = button.getAttribute("toggle-name");
			button.addEventListener("click", () => this.activate(button, name));

			if (button.getAttribute("toggle-default"))
				this.activate(button, name);
		}
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

		this.active[name] = node;
	}
}

const error = {
	init() {
		toggle.init();
	},

}

error.init();
