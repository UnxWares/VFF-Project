{
					__sveltekit_1b8gbyu = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795467122937",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_1b8gbyu);
						const app = await import("/docs/graphql/_app/immutable/entry/app.B8W2ImOv.js");
						kit.start(app, element, {
							node_ids: [0, 3],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
