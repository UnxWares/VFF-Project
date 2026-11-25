{
					__sveltekit_1b7ua7o = {
						base: new URL("..", location).pathname.slice(0, -1),
						version: "1795582374703",
						assets: "/docs/graphql"
					};

					const element = document.currentScript.parentElement;

					import("/docs/graphql/_app/immutable/entry/start.CS_AtC9x.js").then(async (kit) => {
						kit.init(__sveltekit_1b7ua7o);
						const app = await import("/docs/graphql/_app/immutable/entry/app.6gTeVEL-.js");
						kit.start(app, element, {
							node_ids: [0, 6],
							data: [null,null],
							form: null,
							error: null
						});
					});
				}
