<?php

namespace App\Http\Controllers\Voyager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use TCG\Voyager\Facades\Voyager;
use App\Http\Controllers\Voyager\ContentTypes\HandlesContentType;

class Controller extends VoyagerBaseController
{
    // use HandlesContentType;

    public function insertUpdateData($request, $slug, $rows, $data)
    {
        $multi_select = [];
        $multi_select_bc = []; // Backward-compatible multi select for Voyager < 1.1

        /*
         * Prepare Translations and Transform data
         */
        $translations = is_bread_translatable($data)
                        ? $data->prepareTranslations($request)
                        : [];

        foreach ($rows as $row) {
            // if the field for this row is absent from the request, continue
            // checkboxes will be absent when unchecked, thus they are the exception
            if (!$request->hasFile($row->field) && !$request->has($row->field) && $this->checkRowType($row->type)) {
                // if the field is a belongsToMany relationship, don't remove it
                // if no content is provided, that means the relationships need to be removed
                if ((isset($row->details->type) && $row->details->type !== 'belongsToMany') || $row->field !== 'user_belongsto_role_relationship') {
                    continue;
                }
            }

            $content = $this->getContentBasedOnType($request, $slug, $row, $row->details);

            if ($row->type == 'relationship' && $row->details->type != 'belongsToMany') {
                $row->field = @$row->details->column;
            }

            /*
             * merge ex_images and upload images
             */
            if ($row->type == 'multiple_images' && !is_null($content)) {
                if (isset($data->{$row->field})) {
                    $ex_files = json_decode($data->{$row->field}, true);
                    if (!is_null($ex_files)) {
                        $content = json_encode(array_merge($ex_files, json_decode($content)));
                    }
                }
            }

            if (is_null($content)) {

                // If the image upload is null and it has a current image keep the current image
                if ($row->type == 'image' && is_null($request->input($row->field)) && isset($data->{$row->field})) {
                    $content = $data->{$row->field};
                }

                // If the multiple_images upload is null and it has a current image keep the current image
                if ($row->type == 'multiple_images' && is_null($request->input($row->field)) && isset($data->{$row->field})) {
                    $content = $data->{$row->field};
                }

                // If the file upload is null and it has a current file keep the current file
                if ($row->type == 'file') {
                    $content = $data->{$row->field};
                }

                if ($row->type == 'password') {
                    $content = $data->{$row->field};
                }
            }

            if ($row->type == 'relationship' && $row->details->type == 'belongsToMany') {
                // Only if select_multiple is working with a relationship
                $multi_select[] = ['model' => $row->details->model, 'content' => $content, 'table' => $row->details->pivot_table];
            } elseif ($row->type == 'select_multiple' && property_exists($row->details, 'relationship')) {
                // Backward-compatible to older Voyager's select_multiple data row
                $multi_select_bc[] = ['row' => $row->field, 'content' => json_decode($content)];
            } else {
                $data->{$row->field} = $content;
            }
        }

        $data->save();

        // Save translations
        if (count($translations) > 0) {
            $data->saveTranslations($translations);
        }

        if ($slug == 'posts' && $row->type == 'select_multiple' && $row->field == 'post_tag'){
            if (!empty($multi_select_bc) && isset($multi_select_bc[0]['content'])) {
                \App\Post::find($data->id)->{$row->field}()->sync($multi_select_bc[0]['content']);
            }
        } else {
            foreach ($multi_select as $sync_data) {
                $data->belongsToMany($sync_data['model'], $sync_data['table'])->sync($sync_data['content']);
            }
            foreach ($multi_select_bc as $sync_data) {
                $data->{$sync_data['row']}()->sync($sync_data['content']);
            }
        }

        return $data;
    }

    /*
     * Override default voyager view to use custom "read" view instead of default
     * to fix a bug where delete button doesn't work if exists, based on version 1.1.12
     */
    public function show(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.read';

        if (view()->exists("voyager::$slug.read")) {
            $view = "voyager::$slug.read";
        } elseif (view()->exists("voyager::bread.read")) {
            $view = "voyager::bread.read";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

    public function upload(Request $request)
    {
        $fullFilename = null;
        $resizeWidth = 1800;
        $resizeHeight = null;
        $slug = $request->input('type_slug');
        $file = $request->file('image');
        $filename = Str::random(20);
        $fullPath = 'pintaria/'.$slug.'/'.date('F').date('Y').'/'.$filename.'.'.$file->getClientOriginalExtension();

        $ext = $file->guessClientExtension();

        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif'])) {
            $image = Image::make($file)
                ->resize($resizeWidth, $resizeHeight, function (Constraint $constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode($file->getClientOriginalExtension(), 75);

            // move uploaded file from temp to uploads directory
            if (Storage::disk(config('voyager.storage.disk'))->put($fullPath, (string) $image, gcsCachedUploadMetadata())) {
                $status = 'Image successfully uploaded!';
                $fullFilename = $fullPath;
            } else {
                $status = 'Upload Fail: Unknown error occurred!';
            }
        } else {
            $status = 'Upload Fail: Unsupported file format or It is too large to upload!';
        }

        // echo out script that TinyMCE can handle and update the image in the editor
        return "<script> parent.setImageValue('".Voyager::image($fullFilename)."'); </script>";
    }

    public function checkRowType($rowType)
    {
        if ($rowType == 'checkbox') {
            return false;
        } else if ($rowType == 'text') {
            return false;
        } else if ($rowType == 'rich_text_box') {
            return false;
        } else if ($rowType == 'number') {
            return false;
        } else {
            return true;
        }
    }
}
