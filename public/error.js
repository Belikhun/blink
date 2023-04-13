
const toggle = {

	/** @type {Object<string, HTMLElement>} */
	active: {},
	
	/** @type {Object<string, HTMLElement[]>} */
	buttons: {},

	/** @type {HTMLElement} */
	stickyBar: null,

	init() {
		let buttons = document.querySelectorAll(`[toggle-id]`);
		let activated = {}

		for (let button of buttons) {
			let name = button.getAttribute("toggle-name");
			button.addEventListener("click", () => this.activate(button, name));

			if (!this.buttons[name])
				this.buttons[name] = [];

			this.buttons[name].push(button);

			if (button.getAttribute("toggle-default")) {
				this.activate(button, name);
				activated[name] = true;

				if (name === "stacktrace") {
					// Keep the active fault frame in scroll view port.
					// button.parentElement.scrollTop = (button.offsetTop - button.parentElement.offsetTop) + 100;
					button.parentElement.scrollTop = button.offsetTop - button.clientHeight;
				}
			}
		}

		for (let name of Object.keys(this.buttons)) {
			if (activated[name])
				continue;

			this.activate(this.buttons[name][0], name);
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

	init() {
		let links = document.querySelectorAll(`a[nav-link]`);

		for (let link of links) {
			let id = link.getAttribute("nav-target")
				? link.getAttribute("nav-target")
				: link.getAttribute("href").substring(1);
			
			let target = document.getElementById(id);

			if (!target)
				continue;

			this.links[id] = { link, target }
		}

		error.container.addEventListener("scroll", (e) => this.updateScroll(e));
		this.updateScroll({ target: error.container });
	},

	/**
	 * @param {Event} e 
	 */
	updateScroll(e) {
		let scroll = e.target.scrollTop;
		let point = scroll + e.target.clientHeight * 0.5;

		this.container.classList[scroll > 0 ? "add" : "remove"]("scrolling");
		this.container.classList[scroll >= 140 ? "add" : "remove"]("details");
		
		for (let [id, item] of Object.entries(this.links)) {
			let from = item.target.offsetTop;
			let to = from + item.target.clientHeight;

			if (point >= from && point <= to) {
				item.link.classList.add("active");
				item.target.classList.add("active");
			} else {
				item.link.classList.remove("active");
				item.target.classList.remove("active");
			}
		}
	}
}

const copyable = {
	/** @type {HTMLElement} */
	node: undefined,

	/** @type {HTMLElement} */
	hovering: undefined,

	init() {
		this.node = document.createElement("div");
		this.node.classList.add("copy-btn");
		this.node.innerText = "Copy";

		this.node.addEventListener("click", () => {
			if (!this.hovering)
				return;

			let content = this.hovering.getAttribute("copyable");
			navigator.clipboard.writeText(content);
			this.node.innerText = "Copied!";
		});

		let items = document.querySelectorAll(`[copyable]`);

		for (let item of items) {
			item.addEventListener("mouseenter", () => {
				this.hovering = item;
				item.appendChild(this.node);
			});

			item.addEventListener("mouseleave", () => {
				this.hovering = undefined;
				item.removeChild(this.node);
				this.node.innerText = "Copy";
			});
		}
	}
}

const error = {
	container: document.getElementById("app"),

	init() {
		toggle.init();
		nav.init();
		copyable.init();

		if (window.sticker) {
			const handler = () => {
				window.sticker.play();
				document.body.removeEventListener("click", handler);
			};

			document.body.addEventListener("click", handler);
		}

		let reportLink = document.querySelector(`a[data-report-link]`);

		if (reportLink) {
			let inner = reportLink.querySelector(`:scope > span`);
			let value = reportLink.getAttribute(`data-report-link`);
			let reset = null;

			reportLink.addEventListener("click", () => {
				navigator.clipboard.writeText(value);
				inner.innerText = "Report Link Copied!";
				clearTimeout(reset);
				reset = setTimeout(() => inner.innerText = "Copy Report Link", 3000);
			});
		}
	},

}

error.init();
