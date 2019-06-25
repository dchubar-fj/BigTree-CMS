Vue.component("icon", {
	props: ["wrapper", "icon"],
	template:
		`<span :class="wrapper + '_icon'">
			<svg :class="'icon icon_' + icon">
				<use :xlink:href="'admin_root/images/icons.svg#' +  icon"></use>
			</svg>
		</span>`
});