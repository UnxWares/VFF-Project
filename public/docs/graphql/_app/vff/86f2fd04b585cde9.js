{
					__sveltekit_6mei36 = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795558204715",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_6mei36);
						const app = await import("/docs/graphql/_app/immutable/entry/app.wiW7yP92.js");
						kit.start(app, element, {
							node_ids: [0, 8],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
