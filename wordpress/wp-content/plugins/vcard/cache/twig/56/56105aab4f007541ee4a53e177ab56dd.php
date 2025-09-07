<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* profile-default.twig */
class __TwigTemplate_86b5009ef37e41ed9ff1c5f787d52a30 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 2
        yield "<div class=\"vcard-modern-wrapper\">
    <div class=\"vcard-single-container vcard-template-";
        // line 3
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["template_settings"] ?? null), "template_name", [], "any", false, false, false, 3), "html", null, true);
        yield " ";
        yield (((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("vcard-business-profile") : ("vcard-personal-profile"));
        yield "\" data-profile-id=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\">
        
        <article class=\"vcard-single\" data-profile-id=\"";
        // line 5
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\">
            
            ";
        // line 8
        yield "            <div class=\"vcard-header\">
                ";
        // line 9
        if ((($tmp = ($context["thumbnail_url"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 10
            yield "                    <div class=\"vcard-photo\">
                        <img src=\"";
            // line 11
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["thumbnail_url"] ?? null), "html", null, true);
            yield "\" alt=\"";
            yield (((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "business_name", [], "any", false, false, false, 11), "html", null, true)) : ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "first_name", [], "any", false, false, false, 11) . " ") . CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "last_name", [], "any", false, false, false, 11)), "html", null, true)));
            yield "\" />
                    </div>
                ";
        }
        // line 14
        yield "                
                <div class=\"vcard-basic-info\">
                    <h1 class=\"vcard-name\">
                        ";
        // line 17
        if ((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 18
            yield "                            ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "business_name", [], "any", false, false, false, 18)), "html", null, true);
            yield "
                        ";
        } else {
            // line 20
            yield "                            ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(Twig\Extension\CoreExtension::trim(((CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "first_name", [], "any", false, false, false, 20) . " ") . CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "last_name", [], "any", false, false, false, 20)))), "html", null, true);
            yield "
                        ";
        }
        // line 22
        yield "                    </h1>
                    
                    ";
        // line 24
        if ((($context["is_business"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "business_tagline", [], "any", false, false, false, 24))) {
            // line 25
            yield "                        <p class=\"vcard-tagline\">";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "business_tagline", [], "any", false, false, false, 25)), "html", null, true);
            yield "</p>
                    ";
        } elseif ((CoreExtension::getAttribute($this->env, $this->source,         // line 26
($context["basic_info"] ?? null), "job_title", [], "any", false, false, false, 26) || CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "company", [], "any", false, false, false, 26))) {
            // line 27
            yield "                        <p class=\"vcard-title\">
                            ";
            // line 28
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "job_title", [], "any", false, false, false, 28)), "html", null, true);
            yield "
                            ";
            // line 29
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "job_title", [], "any", false, false, false, 29) && CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "company", [], "any", false, false, false, 29))) {
                yield " at ";
            }
            // line 30
            yield "                            ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "company", [], "any", false, false, false, 30)), "html", null, true);
            yield "
                        </p>
                    ";
        }
        // line 33
        yield "                    
                    ";
        // line 35
        yield "                    <div class=\"vcard-stats\">
                        <span class=\"profile-views\">
                            <i class=\"fas fa-eye\"></i>
                            ";
        // line 38
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('format')->getCallable()($this->env->getFunction('__')->getCallable()("%d views", "vcard"), (CoreExtension::getAttribute($this->env, $this->source, ($context["analytics"] ?? null), "profile_views", [], "any", false, false, false, 38) + 1)), "html", null, true);
        yield "
                        </span>
                    </div>
                </div>
            </div>
            
            <div class=\"vcard-content\">
                
                ";
        // line 47
        yield "                ";
        $context["description"] = (((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["basic_info"] ?? null), "business_description", [], "any", false, false, false, 47)) : (CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["profile"] ?? null), "raw_data", [], "any", false, false, false, 47), "content", [], "any", false, false, false, 47)));
        // line 48
        yield "                ";
        if ((($tmp = ($context["description"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 49
            yield "                    <div id=\"about\" class=\"vcard-description vcard-section\">
                        <h3>";
            // line 50
            yield (((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("About Our Business", "vcard"), "html", null, true)) : ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("About", "vcard"), "html", null, true)));
            yield "</h3>
                        ";
            // line 51
            if ((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 52
                yield "                            ";
                yield $this->env->getFilter('wp_kses_post')->getCallable()($this->env->getFilter('wpautop')->getCallable()(($context["description"] ?? null)));
                yield "
                        ";
            } else {
                // line 54
                yield "                            ";
                yield ($context["description"] ?? null);
                yield "
                        ";
            }
            // line 56
            yield "                    </div>
                ";
        }
        // line 58
        yield "                
                ";
        // line 60
        yield "                ";
        if ((($context["is_business"] ?? null) && ($context["has_services"] ?? null))) {
            // line 61
            yield "                    <div id=\"services\" class=\"vcard-services vcard-section\">
                        <h3>";
            // line 62
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Our Services", "vcard"), "html", null, true);
            yield "</h3>
                        <div class=\"services-grid\">
                            ";
            // line 64
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["business_data"] ?? null), "services", [], "any", false, false, false, 64));
            foreach ($context['_seq'] as $context["_key"] => $context["service"]) {
                // line 65
                yield "                                <div class=\"service-item\">
                                    ";
                // line 66
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["service"], "image", [], "any", false, false, false, 66)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 67
                    yield "                                        <div class=\"service-image\">
                                            <img src=\"";
                    // line 68
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "image", [], "any", false, false, false, 68)), "html", null, true);
                    yield "\" alt=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "name", [], "any", false, false, false, 68)), "html", null, true);
                    yield "\">
                                        </div>
                                    ";
                }
                // line 71
                yield "                                    <div class=\"service-content\">
                                        <h4 class=\"service-name\">";
                // line 72
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "name", [], "any", false, false, false, 72)), "html", null, true);
                yield "</h4>
                                        ";
                // line 73
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["service"], "description", [], "any", false, false, false, 73)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 74
                    yield "                                            <p class=\"service-description\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "description", [], "any", false, false, false, 74)), "html", null, true);
                    yield "</p>
                                        ";
                }
                // line 76
                yield "                                        ";
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["service"], "price", [], "any", false, false, false, 76)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 77
                    yield "                                            <div class=\"service-price\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "price", [], "any", false, false, false, 77)), "html", null, true);
                    yield "</div>
                                        ";
                }
                // line 79
                yield "                                        ";
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["service"], "category", [], "any", false, false, false, 79)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 80
                    yield "                                            <div class=\"service-category\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["service"], "category", [], "any", false, false, false, 80)), "html", null, true);
                    yield "</div>
                                        ";
                }
                // line 82
                yield "                                    </div>
                                </div>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_key'], $context['service'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 85
            yield "                        </div>
                    </div>
                ";
        }
        // line 88
        yield "                
                ";
        // line 90
        yield "                ";
        if ((($context["is_business"] ?? null) && ($context["has_products"] ?? null))) {
            // line 91
            yield "                    <div id=\"products\" class=\"vcard-products vcard-section\">
                        <h3>";
            // line 92
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Our Products", "vcard"), "html", null, true);
            yield "</h3>
                        <div class=\"products-grid\">
                            ";
            // line 94
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["business_data"] ?? null), "products", [], "any", false, false, false, 94));
            foreach ($context['_seq'] as $context["_key"] => $context["product"]) {
                // line 95
                yield "                                <div class=\"product-item\">
                                    ";
                // line 96
                if ((CoreExtension::getAttribute($this->env, $this->source, $context["product"], "images", [], "any", false, false, false, 96) && is_iterable(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "images", [], "any", false, false, false, 96)))) {
                    // line 97
                    yield "                                        <div class=\"product-images\">
                                            <img src=\"";
                    // line 98
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()((($_v0 = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "images", [], "any", false, false, false, 98)) && is_array($_v0) || $_v0 instanceof ArrayAccess ? ($_v0[0] ?? null) : null)), "html", null, true);
                    yield "\" alt=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "name", [], "any", false, false, false, 98)), "html", null, true);
                    yield "\">
                                            ";
                    // line 99
                    if ((Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, $context["product"], "images", [], "any", false, false, false, 99)) > 1)) {
                        // line 100
                        yield "                                                <div class=\"product-gallery-indicator\">
                                                    <i class=\"fas fa-images\"></i>
                                                    ";
                        // line 102
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('format')->getCallable()($this->env->getFunction('__')->getCallable()("%d photos", "vcard"), Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, $context["product"], "images", [], "any", false, false, false, 102))), "html", null, true);
                        yield "
                                                </div>
                                            ";
                    }
                    // line 105
                    yield "                                        </div>
                                    ";
                }
                // line 107
                yield "                                    <div class=\"product-content\">
                                        <h4 class=\"product-name\">";
                // line 108
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "name", [], "any", false, false, false, 108)), "html", null, true);
                yield "</h4>
                                        ";
                // line 109
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "description", [], "any", false, false, false, 109)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 110
                    yield "                                            <p class=\"product-description\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "description", [], "any", false, false, false, 110)), "html", null, true);
                    yield "</p>
                                        ";
                }
                // line 112
                yield "                                        <div class=\"product-details\">
                                            ";
                // line 113
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "price", [], "any", false, false, false, 113)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 114
                    yield "                                                <div class=\"product-price\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "price", [], "any", false, false, false, 114)), "html", null, true);
                    yield "</div>
                                            ";
                }
                // line 116
                yield "                                            ";
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "category", [], "any", false, false, false, 116)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 117
                    yield "                                                <div class=\"product-category\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["product"], "category", [], "any", false, false, false, 117)), "html", null, true);
                    yield "</div>
                                            ";
                }
                // line 119
                yield "                                            ";
                if (CoreExtension::getAttribute($this->env, $this->source, $context["product"], "in_stock", [], "any", true, true, false, 119)) {
                    // line 120
                    yield "                                                <div class=\"product-stock ";
                    yield (((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "in_stock", [], "any", false, false, false, 120)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("in-stock") : ("out-of-stock"));
                    yield "\">
                                                    ";
                    // line 121
                    yield (((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["product"], "in_stock", [], "any", false, false, false, 121)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("In Stock", "vcard"), "html", null, true)) : ($this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Out of Stock", "vcard"), "html", null, true)));
                    yield "
                                                </div>
                                            ";
                }
                // line 124
                yield "                                        </div>
                                    </div>
                                </div>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_key'], $context['product'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 128
            yield "                        </div>
                    </div>
                ";
        }
        // line 131
        yield "                
                ";
        // line 133
        yield "                ";
        if ((($context["is_business"] ?? null) && ($context["has_gallery"] ?? null))) {
            // line 134
            yield "                    <div id=\"gallery\" class=\"vcard-gallery vcard-section\">
                        <h3>";
            // line 135
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Gallery", "vcard"), "html", null, true);
            yield "</h3>
                        <div class=\"gallery-grid\">
                            ";
            // line 137
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["business_data"] ?? null), "gallery", [], "any", false, false, false, 137));
            foreach ($context['_seq'] as $context["_key"] => $context["item"]) {
                // line 138
                yield "                                <div class=\"gallery-item\">
                                    <img src=\"";
                // line 139
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()((((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "thumbnail_url", [], "any", true, true, false, 139) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, $context["item"], "thumbnail_url", [], "any", false, false, false, 139)))) ? (CoreExtension::getAttribute($this->env, $this->source, $context["item"], "thumbnail_url", [], "any", false, false, false, 139)) : (CoreExtension::getAttribute($this->env, $this->source, $context["item"], "image_url", [], "any", false, false, false, 139)))), "html", null, true);
                yield "\" 
                                         alt=\"";
                // line 140
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()(((CoreExtension::getAttribute($this->env, $this->source, $context["item"], "title", [], "any", true, true, false, 140)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 140), "")) : (""))), "html", null, true);
                yield "\"
                                         data-full=\"";
                // line 141
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "image_url", [], "any", false, false, false, 141)), "html", null, true);
                yield "\">
                                    ";
                // line 142
                if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 142)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 143
                    yield "                                        <div class=\"gallery-item-title\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 143)), "html", null, true);
                    yield "</div>
                                    ";
                }
                // line 145
                yield "                                </div>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_key'], $context['item'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 147
            yield "                        </div>
                    </div>
                ";
        }
        // line 150
        yield "                
                ";
        // line 152
        yield "                ";
        if ((($context["is_business"] ?? null) && ($context["has_business_hours"] ?? null))) {
            // line 153
            yield "                    <div id=\"hours\" class=\"vcard-business-hours vcard-section\">
                        <h3>";
            // line 154
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Business Hours", "vcard"), "html", null, true);
            yield "</h3>
                        <div class=\"business-hours-list\">
                            ";
            // line 156
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["business_hours"] ?? null));
            foreach ($context['_seq'] as $context["day"] => $context["schedule"]) {
                // line 157
                yield "                                <div class=\"business-hours-item\">
                                    <span class=\"day-label\">";
                // line 158
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["schedule"], "label", [], "any", false, false, false, 158)), "html", null, true);
                yield "</span>
                                    <span class=\"hours-status ";
                // line 159
                yield (((($tmp = CoreExtension::getAttribute($this->env, $this->source, $context["schedule"], "closed", [], "any", false, false, false, 159)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("closed") : ("open"));
                yield "\">
                                        ";
                // line 160
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["schedule"], "status", [], "any", false, false, false, 160)), "html", null, true);
                yield "
                                    </span>
                                </div>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['day'], $context['schedule'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 164
            yield "                        </div>
                    </div>
                ";
        }
        // line 167
        yield "                
                ";
        // line 169
        yield "                <div id=\"contact\" class=\"vcard-contact-info vcard-section\">
                    <h3>";
        // line 170
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Contact Information", "vcard"), "html", null, true);
        yield "</h3>
                    
                    ";
        // line 172
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["contact_info"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["contact"]) {
            // line 173
            yield "                        <div class=\"vcard-contact-item\">
                            <i class=\"";
            // line 174
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "icon", [], "any", false, false, false, 174), "html", null, true);
            yield "\"></i>
                            <div class=\"contact-details\">
                                <strong>";
            // line 176
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "label", [], "any", false, false, false, 176)), "html", null, true);
            yield ":</strong>
                                ";
            // line 177
            if (CoreExtension::inFilter(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "type", [], "any", false, false, false, 177), ["tel", "email", "whatsapp", "url"])) {
                // line 178
                yield "                                    <a href=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "link", [], "any", false, false, false, 178)), "html", null, true);
                yield "\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "value", [], "any", false, false, false, 178)), "html", null, true);
                yield "</a>
                                ";
            } else {
                // line 180
                yield "                                    ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["contact"], "value", [], "any", false, false, false, 180)), "html", null, true);
                yield "
                                ";
            }
            // line 182
            yield "                            </div>
                        </div>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_key'], $context['contact'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 185
        yield "                    
                    ";
        // line 187
        yield "                    ";
        if ((($tmp = ($context["has_social_media"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 188
            yield "                        <div class=\"vcard-social-media\">
                            <h4>";
            // line 189
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Follow Us", "vcard"), "html", null, true);
            yield "</h4>
                            <div class=\"social-links\">
                                ";
            // line 191
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["social_media"] ?? null));
            foreach ($context['_seq'] as $context["platform"] => $context["data"]) {
                // line 192
                yield "                                    <a href=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_url')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["data"], "url", [], "any", false, false, false, 192)), "html", null, true);
                yield "\" target=\"_blank\" rel=\"noopener\" class=\"social-link social-";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["platform"], "html", null, true);
                yield "\">
                                        <i class=\"";
                // line 193
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["data"], "icon_class", [], "any", false, false, false, 193), "html", null, true);
                yield "\"></i>
                                        <span class=\"sr-only\">";
                // line 194
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["data"], "platform", [], "any", false, false, false, 194), "html", null, true);
                yield "</span>
                                    </a>
                                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['platform'], $context['data'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 197
            yield "                            </div>
                        </div>
                    ";
        }
        // line 200
        yield "                </div>
                
                ";
        // line 203
        yield "                ";
        if ((($tmp = ($context["has_address"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 204
            yield "                    <div class=\"vcard-address\">
                        <h3><i class=\"fas fa-map-marker-alt\"></i> ";
            // line 205
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Address", "vcard"), "html", null, true);
            yield "</h3>
                        <div class=\"vcard-address-details\">
                            ";
            // line 207
            if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "address", [], "any", false, false, false, 207)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 208
                yield "                                <div class=\"address-line\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "address", [], "any", false, false, false, 208)), "html", null, true);
                yield "</div>
                            ";
            }
            // line 210
            yield "                            <div class=\"address-line\">
                                ";
            // line 211
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "city", [], "any", false, false, false, 211)), "html", null, true);
            yield "
                                ";
            // line 212
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "city", [], "any", false, false, false, 212) && CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "state", [], "any", false, false, false, 212))) {
                yield ", ";
            }
            // line 213
            yield "                                ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "state", [], "any", false, false, false, 213)), "html", null, true);
            yield "
                                ";
            // line 214
            if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "zip_code", [], "any", false, false, false, 214)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "zip_code", [], "any", false, false, false, 214)), "html", null, true);
            }
            // line 215
            yield "                            </div>
                            ";
            // line 216
            if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "country", [], "any", false, false, false, 216)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 217
                yield "                                <div class=\"address-line\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "country", [], "any", false, false, false, 217)), "html", null, true);
                yield "</div>
                            ";
            }
            // line 219
            yield "                            
                            ";
            // line 221
            yield "                            ";
            if ((CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "latitude", [], "any", false, false, false, 221) && CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "longitude", [], "any", false, false, false, 221))) {
                // line 222
                yield "                                <div class=\"address-map\">
                                    <a href=\"https://maps.google.com/?q=";
                // line 223
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "latitude", [], "any", false, false, false, 223)), "html", null, true);
                yield ",";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, ($context["address"] ?? null), "longitude", [], "any", false, false, false, 223)), "html", null, true);
                yield "\" 
                                       target=\"_blank\" rel=\"noopener\" class=\"map-link\">
                                        <i class=\"fas fa-external-link-alt\"></i>
                                        ";
                // line 226
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("View on Map", "vcard"), "html", null, true);
                yield "
                                    </a>
                                </div>
                            ";
            }
            // line 230
            yield "                        </div>
                    </div>
                ";
        }
        // line 233
        yield "                
                ";
        // line 235
        yield "                ";
        if ((($context["is_business"] ?? null) && (CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "contact_form_enabled", [], "any", false, false, false, 235) != "0"))) {
            // line 236
            yield "                    <div class=\"vcard-contact-form\">
                        <h3>";
            // line 237
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_html')->getCallable()(((CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "contact_form_title", [], "any", true, true, false, 237)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "contact_form_title", [], "any", false, false, false, 237), $this->env->getFunction('__')->getCallable()("Leave a Message", "vcard"))) : ($this->env->getFunction('__')->getCallable()("Leave a Message", "vcard")))), "html", null, true);
            yield "</h3>
                        <form id=\"vcard-contact-form\" class=\"contact-form\" method=\"post\" action=\"\">
                            <input type=\"hidden\" name=\"vcard_contact_nonce\" value=\"";
            // line 239
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["contact_form_nonce"] ?? null), "html", null, true);
            yield "\">
                            <input type=\"hidden\" name=\"profile_id\" value=\"";
            // line 240
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
            yield "\">
                            
                            <div class=\"form-row\">
                                <div class=\"form-group\">
                                    <label for=\"contact_name\">";
            // line 244
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Your Name", "vcard"), "html", null, true);
            yield " <span class=\"required\">*</span></label>
                                    <input type=\"text\" id=\"contact_name\" name=\"contact_name\" required>
                                </div>
                                <div class=\"form-group\">
                                    <label for=\"contact_email\">";
            // line 248
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Your Email", "vcard"), "html", null, true);
            yield " <span class=\"required\">*</span></label>
                                    <input type=\"email\" id=\"contact_email\" name=\"contact_email\" required>
                                </div>
                            </div>
                            
                            <div class=\"form-row\">
                                <div class=\"form-group\">
                                    <label for=\"contact_phone\">";
            // line 255
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Your Phone", "vcard"), "html", null, true);
            yield "</label>
                                    <input type=\"tel\" id=\"contact_phone\" name=\"contact_phone\">
                                </div>
                                <div class=\"form-group\">
                                    <label for=\"contact_subject\">";
            // line 259
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Subject", "vcard"), "html", null, true);
            yield "</label>
                                    <input type=\"text\" id=\"contact_subject\" name=\"contact_subject\">
                                </div>
                            </div>
                            
                            <div class=\"form-group\">
                                <label for=\"contact_message\">";
            // line 265
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Message", "vcard"), "html", null, true);
            yield " <span class=\"required\">*</span></label>
                                <textarea id=\"contact_message\" name=\"contact_message\" rows=\"5\" required placeholder=\"";
            // line 266
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFilter('esc_attr')->getCallable()($this->env->getFunction('__')->getCallable()("Tell us about your inquiry...", "vcard")), "html", null, true);
            yield "\"></textarea>
                            </div>
                            
                            ";
            // line 270
            yield "                            <div class=\"honeypot-field\" style=\"display: none;\">
                                <label for=\"website_url\">";
            // line 271
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Website", "vcard"), "html", null, true);
            yield "</label>
                                <input type=\"text\" id=\"website_url\" name=\"website_url\" tabindex=\"-1\" autocomplete=\"off\">
                            </div>
                            
                            <div class=\"form-actions\">
                                <button type=\"submit\" class=\"contact-submit-btn\">
                                    <i class=\"fas fa-paper-plane\"></i>
                                    ";
            // line 278
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Send Message", "vcard"), "html", null, true);
            yield "
                                </button>
                                <div class=\"form-loading\" style=\"display: none;\">
                                    <i class=\"fas fa-spinner fa-spin\"></i>
                                    ";
            // line 282
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Sending...", "vcard"), "html", null, true);
            yield "
                                </div>
                            </div>
                            
                            <div class=\"form-messages\"></div>
                        </form>
                    </div>
                ";
        }
        // line 290
        yield "                
                ";
        // line 292
        yield "                <div class=\"vcard-actions\">
                    ";
        // line 294
        yield "                    <div class=\"vcard-contact-management\">
                        <button class=\"vcard-save-contact-btn\" data-profile-id=\"";
        // line 295
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\">
                            <i class=\"fas fa-bookmark\"></i>
                            ";
        // line 297
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Save Contact", "vcard"), "html", null, true);
        yield "
                        </button>
                        
                        <button class=\"vcard-view-contacts-btn\">
                            <i class=\"fas fa-address-book\"></i>
                            ";
        // line 302
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("My Contacts", "vcard"), "html", null, true);
        yield "
                            <span class=\"vcard-contact-count\" style=\"display: none;\">0</span>
                        </button>
                    </div>
                    
                    <div class=\"vcard-download-group\">
                        <button class=\"vcard-download-btn\" data-profile-id=\"";
        // line 308
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\" data-format=\"vcf\">
                            <i class=\"fas fa-download\"></i>
                            ";
        // line 310
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Download vCard", "vcard"), "html", null, true);
        yield "
                        </button>
                        <div class=\"vcard-download-options\">
                            <button class=\"vcard-export-vcf\" data-profile-id=\"";
        // line 313
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\">
                                <i class=\"fas fa-file-alt\"></i>
                                ";
        // line 315
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("VCF Format", "vcard"), "html", null, true);
        yield "
                            </button>
                            <button class=\"vcard-export-csv\" data-profile-id=\"";
        // line 317
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
        yield "\">
                                <i class=\"fas fa-table\"></i>
                                ";
        // line 319
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("CSV Format", "vcard"), "html", null, true);
        yield "
                            </button>
                        </div>
                    </div>
                    <button class=\"vcard-share-btn\" onclick=\"shareProfile()\">
                        <i class=\"fas fa-share-alt\"></i>
                        ";
        // line 325
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Share Profile", "vcard"), "html", null, true);
        yield "
                    </button>
                    ";
        // line 327
        if ((($tmp = ($context["is_business"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 328
            yield "                        <button class=\"vcard-qr-btn\" data-profile-id=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["profile_id"] ?? null), "html", null, true);
            yield "\">
                            <i class=\"fas fa-qrcode\"></i>
                            ";
            // line 330
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("QR Code", "vcard"), "html", null, true);
            yield "
                        </button>
                    ";
        }
        // line 333
        yield "                </div>
            </div>
        </article>
    </div>
</div>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "profile-default.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  825 => 333,  819 => 330,  813 => 328,  811 => 327,  806 => 325,  797 => 319,  792 => 317,  787 => 315,  782 => 313,  776 => 310,  771 => 308,  762 => 302,  754 => 297,  749 => 295,  746 => 294,  743 => 292,  740 => 290,  729 => 282,  722 => 278,  712 => 271,  709 => 270,  703 => 266,  699 => 265,  690 => 259,  683 => 255,  673 => 248,  666 => 244,  659 => 240,  655 => 239,  650 => 237,  647 => 236,  644 => 235,  641 => 233,  636 => 230,  629 => 226,  621 => 223,  618 => 222,  615 => 221,  612 => 219,  606 => 217,  604 => 216,  601 => 215,  596 => 214,  591 => 213,  587 => 212,  583 => 211,  580 => 210,  574 => 208,  572 => 207,  567 => 205,  564 => 204,  561 => 203,  557 => 200,  552 => 197,  543 => 194,  539 => 193,  532 => 192,  528 => 191,  523 => 189,  520 => 188,  517 => 187,  514 => 185,  506 => 182,  500 => 180,  492 => 178,  490 => 177,  486 => 176,  481 => 174,  478 => 173,  474 => 172,  469 => 170,  466 => 169,  463 => 167,  458 => 164,  448 => 160,  444 => 159,  440 => 158,  437 => 157,  433 => 156,  428 => 154,  425 => 153,  422 => 152,  419 => 150,  414 => 147,  407 => 145,  401 => 143,  399 => 142,  395 => 141,  391 => 140,  387 => 139,  384 => 138,  380 => 137,  375 => 135,  372 => 134,  369 => 133,  366 => 131,  361 => 128,  352 => 124,  346 => 121,  341 => 120,  338 => 119,  332 => 117,  329 => 116,  323 => 114,  321 => 113,  318 => 112,  312 => 110,  310 => 109,  306 => 108,  303 => 107,  299 => 105,  293 => 102,  289 => 100,  287 => 99,  281 => 98,  278 => 97,  276 => 96,  273 => 95,  269 => 94,  264 => 92,  261 => 91,  258 => 90,  255 => 88,  250 => 85,  242 => 82,  236 => 80,  233 => 79,  227 => 77,  224 => 76,  218 => 74,  216 => 73,  212 => 72,  209 => 71,  201 => 68,  198 => 67,  196 => 66,  193 => 65,  189 => 64,  184 => 62,  181 => 61,  178 => 60,  175 => 58,  171 => 56,  165 => 54,  159 => 52,  157 => 51,  153 => 50,  150 => 49,  147 => 48,  144 => 47,  133 => 38,  128 => 35,  125 => 33,  118 => 30,  114 => 29,  110 => 28,  107 => 27,  105 => 26,  100 => 25,  98 => 24,  94 => 22,  88 => 20,  82 => 18,  80 => 17,  75 => 14,  67 => 11,  64 => 10,  62 => 9,  59 => 8,  54 => 5,  45 => 3,  42 => 2,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "profile-default.twig", "/var/www/html/wp-content/plugins/vcard/templates/twig/profile-default.twig");
    }
}
