
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

const nav = {
	container: document.querySelector(`#app > header`),
	links: {},
	active: null,

	init() {
		let links = document.querySelectorAll(`a[nav-link]`);

		for (let link of links) {
			let id = link.getAttribute("href").substring(1);
			let target = document.getElementById(id);

			if (!target)
				continue;

			this.links[id] = { link, target }
		}

		error.container.addEventListener("scroll", (e) => this.updateScroll(e));
	},

	/**
	 * @param {Event} e 
	 */
	updateScroll(e) {
		let scroll = e.target.scrollTop;
		let point = scroll + 200;
		let found = false;

		this.container.classList[scroll > 0 ? "add" : "remove"]("scrolling");
		this.container.classList[scroll >= 140 ? "add" : "remove"]("details");
		
		for (let [id, item] of Object.entries(this.links)) {
			let from = item.target.offsetTop;
			let to = from + item.target.clientHeight;

			if (point >= from && point <= to) {
				if (this.active) {
					this.active.link.classList.remove("active");
					this.active.target.classList.remove("active");
				}

				item.link.classList.add("active");
				item.target.classList.add("active");
				this.active = item;
				found = true;
				break;
			}
		}

		if (!found && this.active) {
			this.active.link.classList.remove("active");
			this.active.target.classList.remove("active");
		}
	}
}

const error = {
	container: document.getElementById("app"),

	init() {
		toggle.init();
		nav.init();

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
